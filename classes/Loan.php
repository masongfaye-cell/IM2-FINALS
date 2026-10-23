<?php
class Loan {
    public function __construct(private PDO $pdo) {}
    public function list(?int $patron = null, string $search = '', string $state = ''): array {
        $sql=loan_query($patron===null ? 'WHERE l.archived_at IS NULL' : 'WHERE 1=1'); $args=[];
        if ($patron !== null) { $sql.=' AND l.patron_id=?'; $args[]=$patron; }
        if ($search!=='') { $sql.=' AND (b.title LIKE ? OR b.isbn LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.email LIKE ?)';
            $args=array_merge($args,array_fill(0,5,'%'.$search.'%')); }
        if ($state==='Overdue') $sql.=" AND l.status='Active' AND l.due_date < CURDATE()";
        elseif ($state==='Active') $sql.=" AND l.status='Active'";
        elseif ($state==='Returned') $sql.=" AND l.status='Returned'";
        return rows($this->pdo, $sql.' ORDER BY l.issue_date DESC,l.loan_id DESC', $args);
    }
    public function issue(int $bookId, int $patronId, int $librarianId, string $due): void {
        if (!safe_date($due) || $due <= date('Y-m-d')) throw new DomainException('Due date must be after today.');
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT title,status,archived_at FROM books WHERE book_id=? FOR UPDATE');
            $stmt->execute([$bookId]); $book=$stmt->fetch();
            if (!$book || $book['archived_at']!==null || $book['status']!=='Available') throw new DomainException('This book is not currently available.');
            $stmt=$this->pdo->prepare("SELECT u.user_id FROM users u JOIN roles r USING(role_id)
                WHERE u.user_id=? AND r.role_name='Patron' AND u.account_status='Active' AND u.archived_at IS NULL");
            $stmt->execute([$patronId]);
            if (!$stmt->fetch()) throw new DomainException('Select an active registered patron.');
            $overdue=(int)scalar($this->pdo, "SELECT COUNT(*) FROM loans WHERE patron_id=? AND status='Active' AND due_date<CURDATE()",[$patronId]);
            if ($overdue) throw new DomainException('This patron has an overdue loan. Resolve it before issuing another.');
            $update=$this->pdo->prepare("UPDATE books SET status='On Loan' WHERE book_id=? AND status='Available'");
            $update->execute([$bookId]);
            if ($update->rowCount()!==1) throw new DomainException('Book availability changed. Please refresh.');
            $this->pdo->prepare("INSERT INTO loans(book_id,patron_id,issued_by,issue_date,due_date,status)
                VALUES(?,?,?,CURDATE(),?,'Active')")->execute([$bookId,$patronId,$librarianId,$due]);
            $id=(int)$this->pdo->lastInsertId();
            log_action($this->pdo,"Issued loan #$id for book #$bookId to patron #$patronId");
            $this->pdo->commit();
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function returnBook(int $loanId): void {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT l.*, b.title FROM loans l JOIN books b USING(book_id) WHERE loan_id=? FOR UPDATE');
            $stmt->execute([$loanId]); $loan=$stmt->fetch();
            if (!$loan || $loan['status']!=='Active') throw new DomainException('Loan is not active or was already returned.');
            $days=max(0,(int)scalar($this->pdo,'SELECT DATEDIFF(CURDATE(), ?)',[$loan['due_date']]));
            $fine=round($days*fine_rate(),2);
            $this->pdo->prepare("UPDATE loans SET status='Returned',return_date=CURDATE(),fine_amount=? WHERE loan_id=?")
                ->execute([$fine,$loanId]);
            $this->pdo->prepare("UPDATE books SET status='Available' WHERE book_id=?")->execute([$loan['book_id']]);
            log_action($this->pdo,"Returned loan #$loanId, late fee " . number_format($fine,2) . ' PHP');
            $this->pdo->commit();
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function changeDue(int $loanId, string $due): void {
        if (!safe_date($due) || $due < date('Y-m-d')) throw new DomainException('Enter a valid due date that is today or later.');
        $stmt=$this->pdo->prepare("UPDATE loans SET due_date=? WHERE loan_id=? AND status='Active'");
        $stmt->execute([$due,$loanId]);
        if ($stmt->rowCount()!==1) throw new DomainException('Active loan not found or date unchanged.');
        log_action($this->pdo,"Extended due date for loan #$loanId to $due");
    }
    public function settle(int $loanId, float $amount, string $type): void {
        if (!in_array($type,['pay','waive'],true) || $amount<=0 || !is_finite($amount)) throw new DomainException('Enter a positive valid amount.');
        $amount=round($amount,2);
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT * FROM loans WHERE loan_id=? FOR UPDATE');
            $stmt->execute([$loanId]); $loan=$stmt->fetch();
            if (!$loan || $loan['status']!=='Returned') throw new DomainException('Fines can be settled after the book is returned.');
            $balance=money_owed($loan);
            if ($amount > $balance+0.001) throw new DomainException('Amount exceeds the outstanding balance of ' . money($balance));
            $field=$type==='pay'?'fine_paid':'fine_waived';
            $this->pdo->prepare("UPDATE loans SET $field=$field+? WHERE loan_id=?")->execute([$amount,$loanId]);
            log_action($this->pdo, ($type==='pay'?'Recorded fine payment':'Waived late fee') . " of " . number_format($amount,2) . " PHP on loan #$loanId");
            $this->pdo->commit();
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function archive(int $loanId): void {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT * FROM loans WHERE loan_id=? FOR UPDATE');
            $stmt->execute([$loanId]); $loan=$stmt->fetch();
            if (!$loan || $loan['status']!=='Returned' || $loan['archived_at']!==null)
                throw new DomainException('Only unarchived returned loans can be archived.');
            if (money_owed($loan)>0)
                throw new DomainException('Settle or waive the outstanding late fee before archiving this loan.');
            $this->pdo->prepare('UPDATE loans SET archived_at=NOW() WHERE loan_id=?')->execute([$loanId]);
            log_action($this->pdo,"Archived returned loan #$loanId");
            $this->pdo->commit();
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

}
