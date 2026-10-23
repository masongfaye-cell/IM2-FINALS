<?php
class User {
    public function __construct(private PDO $pdo) {}
    public function listPatrons(string $search = ''): array {
        $sql="SELECT u.*, r.role_name, (SELECT COUNT(*) FROM loans l WHERE l.patron_id=u.user_id AND l.status='Active') AS active_loans
           FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='Patron' AND u.archived_at IS NULL";
        $params=[];
        if ($search!=='') { $sql.=' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $params=array_fill(0,3,'%'.$search.'%'); }
        return rows($this->pdo,$sql.' ORDER BY u.created_at DESC',$params);
    }
    public function register(array $data): void {
        $first=trim((string)($data['first_name']??'')); $last=trim((string)($data['last_name']??''));
        $email=strtolower(trim((string)($data['email']??''))); $phone=trim((string)($data['phone']??''));
        $password=(string)($data['password']??'');
        if (!$first || !$last || strlen($first)>50 || strlen($last)>50 || !validate_email($email) || strlen($email)>100 || strlen($phone)>20)
            throw new DomainException('Enter a valid member name, email and phone.');
        if (strlen($password)<10) throw new DomainException('Initial password must be at least 10 characters.');
        $role=scalar($this->pdo,"SELECT role_id FROM roles WHERE role_name='Patron'");
        if (!$role) throw new DomainException('Missing Patron role. Import schema.sql first.');
        try {
            $this->pdo->prepare("INSERT INTO users(role_id,first_name,last_name,email,phone,password_hash,account_status)
                VALUES(?,?,?,?,?,?,'Active')")->execute([$role,$first,$last,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
            log_action($this->pdo,'Registered patron #' . $this->pdo->lastInsertId() . ": $first $last");
        } catch (PDOException $e) {
            if (($e->errorInfo[1]??0)==1062) throw new DomainException('That email address is already registered.');
            throw $e;
        }
    }
    public function update(int $id, array $data): void {
        $first=trim((string)($data['first_name']??'')); $last=trim((string)($data['last_name']??''));
        $email=strtolower(trim((string)($data['email']??''))); $phone=trim((string)($data['phone']??''));
        if (!$first || !$last || strlen($first)>50 || strlen($last)>50 || !validate_email($email) || strlen($email)>100 || strlen($phone)>20)
            throw new DomainException('Enter valid member details.');
        try {
            $stmt=$this->pdo->prepare("UPDATE users u JOIN roles r ON r.role_id=u.role_id
               SET u.first_name=?,u.last_name=?,u.email=?,u.phone=?
               WHERE u.user_id=? AND r.role_name='Patron' AND u.archived_at IS NULL");
            $stmt->execute([$first,$last,$email,$phone,$id]);
            if (!$stmt->rowCount() && !scalar($this->pdo,'SELECT user_id FROM users WHERE user_id=? AND archived_at IS NULL',[$id])) throw new DomainException('Member not found.');
            log_action($this->pdo,"Updated patron #$id: $first $last");
        } catch (PDOException $e) {
            if (($e->errorInfo[1]??0)==1062) throw new DomainException('That email address is already registered.');
            throw $e;
        }
    }
    public function status(int $id, string $status): void {
        if (!in_array($status,['Active','Suspended'],true)) throw new DomainException('Invalid account status.');
        $stmt=$this->pdo->prepare("UPDATE users u JOIN roles r ON r.role_id=u.role_id SET u.account_status=?
            WHERE u.user_id=? AND r.role_name='Patron' AND u.archived_at IS NULL");
        $stmt->execute([$status,$id]);
        if (!$stmt->rowCount()) throw new DomainException('Member not found or status unchanged.');
        log_action($this->pdo,"Set patron #$id status to $status");
    }
    public function archive(int $id): void {
        $this->pdo->beginTransaction();
        try {
            $active=(int)scalar($this->pdo,"SELECT COUNT(*) FROM loans WHERE patron_id=? AND status='Active'",[$id]);
            $unpaid=(float)scalar($this->pdo,"SELECT COALESCE(SUM(GREATEST(0,fine_amount-fine_paid-fine_waived)),0) FROM loans WHERE patron_id=? AND status='Returned'",[$id]);
            if ($active || $unpaid>0) throw new DomainException('Return all books and settle outstanding fees before archiving this patron.');
            $stmt=$this->pdo->prepare("UPDATE users u JOIN roles r ON r.role_id=u.role_id SET u.archived_at=NOW(),u.account_status='Suspended'
               WHERE u.user_id=? AND r.role_name='Patron' AND u.archived_at IS NULL");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) throw new DomainException('Patron not found.');
            log_action($this->pdo,"Archived patron #$id");
            $this->pdo->commit();
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function resetPassword(int $id, string $password): void {
        if (strlen($password)<10) throw new DomainException('New password must contain at least 10 characters.');
        $stmt=$this->pdo->prepare("UPDATE users u JOIN roles r ON r.role_id=u.role_id
           SET u.password_hash=? WHERE u.user_id=? AND r.role_name='Patron' AND u.archived_at IS NULL");
        $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        if (!$stmt->rowCount()) throw new DomainException('Patron not found.');
        log_action($this->pdo,"Reset login password for patron #$id");
    }

}
