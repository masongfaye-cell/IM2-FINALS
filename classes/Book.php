<?php
class Book {
    public function __construct(private PDO $pdo) {}
    public function list(string $search = '', string $status = ''): array {
        $sql = 'SELECT b.*, c.category_name, p.name AS publisher_name FROM books b
                LEFT JOIN categories c ON c.category_id=b.category_id
                LEFT JOIN publishers p ON p.publisher_id=b.publisher_id WHERE b.archived_at IS NULL';
        $args=[];
        if ($search !== '') {
            $sql .= ' AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR c.category_name LIKE ? OR p.name LIKE ?)';
            $args = array_fill(0,5,'%' . $search . '%');
        }
        if (in_array($status, ['Available', 'On Loan'], true)) { $sql .= ' AND b.status=?'; $args[]=$status; }
        return rows($this->pdo, $sql . ' ORDER BY b.title ASC', $args);
    }
    private function resolve(string $table, string $column, string $id, string $name): int {
        $name = trim($name);
        if ($name === '' || strlen($name) > 100) throw new DomainException('Category and publisher are required (100 characters maximum).');
        $stmt=$this->pdo->prepare("SELECT $id FROM $table WHERE $column=? LIMIT 1");
        $stmt->execute([$name]); $existing=$stmt->fetchColumn();
        if ($existing !== false) return (int) $existing;
        $stmt=$this->pdo->prepare("INSERT INTO $table ($column) VALUES (?)");
        try { $stmt->execute([$name]); return (int) $this->pdo->lastInsertId(); }
        catch (PDOException $e) {
            $stmt=$this->pdo->prepare("SELECT $id FROM $table WHERE $column=? LIMIT 1");
            $stmt->execute([$name]); $found=$stmt->fetchColumn();
            if ($found !== false) return (int) $found;
            throw $e;
        }
    }
    public function save(array $form, ?int $id = null): void {
        $title=trim((string)($form['title']??'')); $author=trim((string)($form['author']??''));
        $isbn=trim((string)($form['isbn']??'')); $shelf=trim((string)($form['shelf_location']??''));
        if (!$title || !$author || !$isbn || strlen($title)>255 || strlen($author)>255 || strlen($isbn)>20 || strlen($shelf)>80)
            throw new DomainException('Enter valid title, author, ISBN (maximum 20 characters), and shelf location.');
        $this->pdo->beginTransaction();
        try {
            $category = $this->resolve('categories','category_name','category_id',(string)($form['category_name']??''));
            $publisher = $this->resolve('publishers','name','publisher_id',(string)($form['publisher_name']??''));
            if ($id !== null) {
                if (!scalar($this->pdo, 'SELECT book_id FROM books WHERE book_id=? AND archived_at IS NULL', [$id]))
                    throw new DomainException('Book not found.');
                $this->pdo->prepare('UPDATE books SET title=?,author=?,isbn=?,category_id=?,publisher_id=?,shelf_location=? WHERE book_id=?')
                    ->execute([$title,$author,$isbn,$category,$publisher,$shelf,$id]);
                log_action($this->pdo,"Updated book #$id: $title");
            } else {
                $this->pdo->prepare("INSERT INTO books (title,author,isbn,category_id,publisher_id,shelf_location,status) VALUES (?,?,?,?,?,?,'Available')")
                    ->execute([$title,$author,$isbn,$category,$publisher,$shelf]);
                $id=(int)$this->pdo->lastInsertId(); log_action($this->pdo,"Added book #$id: $title");
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) == 1062) throw new DomainException('That ISBN or category/publisher already exists.');
            throw $e;
        }
    }
    public function archive(int $id): void {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare('SELECT title,status FROM books WHERE book_id=? AND archived_at IS NULL FOR UPDATE');
            $stmt->execute([$id]); $book=$stmt->fetch();
            if (!$book) throw new DomainException('Book not found.');
            if ($book['status']==='On Loan' || scalar($this->pdo,"SELECT COUNT(*) FROM loans WHERE book_id=? AND status='Active'",[$id]))
                throw new DomainException('Return this book before archiving it.');
            $this->pdo->prepare('UPDATE books SET archived_at=NOW() WHERE book_id=?')->execute([$id]);
            log_action($this->pdo,"Archived book #$id: {$book['title']}");
            $this->pdo->commit();
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
