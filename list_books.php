<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Library Books';

function lb_col_exists($t, $c) {
    $r = db_query("SHOW COLUMNS FROM `$t` LIKE '" . str_replace("'", '', $c) . "'");
    return $r && $r->num_rows > 0;
}
try {
    foreach (['book_no' => 'VARCHAR(50) NULL', 'isbn' => 'VARCHAR(50) NULL', 'publisher' => 'VARCHAR(191) NULL',
              'subject' => 'VARCHAR(191) NULL', 'department' => 'VARCHAR(191) NULL', 'rack_shelf' => 'VARCHAR(50) NULL',
              'price' => 'DECIMAL(12,2) DEFAULT 0', 'added_on' => 'DATE NULL', 'cover_image' => 'VARCHAR(191) NULL'] as $c => $d) {
        if (!lb_col_exists('books', $c)) { db_query("ALTER TABLE books ADD COLUMN `$c` $d"); }
    }
} catch (Throwable $ex) {}

$books = [];
$sql = "SELECT b.*,
        (SELECT COUNT(*) FROM book_issues bi WHERE bi.book_id=b.book_id AND COALESCE(bi.status,'')='issued') AS issued
        FROM books b ORDER BY b.title ASC, b.book_id ASC";
$res = db_query($sql);
while ($row = $res->fetch_assoc()) {
    $row['available'] = (int) ($row['available'] ?? 0);
    $row['quantity'] = (int) ($row['quantity'] ?? 0);
    $row['issued'] = (int) ($row['issued'] ?? 0);
    $books[] = $row;
}

if (($_GET['action'] ?? '') === 'export') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="books_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Book No.', 'Title', 'Author', 'ISBN', 'Publisher', 'Subject', 'Department', 'Rack/Shelf', 'Category', 'Total Qty', 'Available', 'Issued', 'Price', 'Added On', 'Status']);
    foreach ($books as $b) {
        $status = $b['available'] > 0 ? 'Available' : 'Issued';
        fputcsv($out, [
            $b['book_no'] ?? '', $b['title'], $b['author'] ?? '', $b['isbn'] ?? '', $b['publisher'] ?? '',
            $b['subject'] ?? '', $b['department'] ?? '', $b['rack_shelf'] ?? '', $b['category'] ?? '',
            $b['quantity'], $b['available'], $b['issued'],
            isset($b['price']) ? number_format((float) $b['price'], 2, '.', '') : '',
            $b['added_on'] ?? '', $status
        ]);
    }
    fclose($out);
    exit;
}

$message = '';
$error = '';

function lb_books_dir() {
    return __DIR__ . '/uploads/books';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddBook') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $book_no = trim($_POST['book_no'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $rack_shelf = trim($_POST['rack_shelf'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 1);
        if ($quantity < 1) { $quantity = 1; }
        $available = isset($_POST['available']) ? (int) $_POST['available'] : $quantity;
        $price = (float) ($_POST['price'] ?? 0);
        $added_on = trim($_POST['added_on'] ?? '');
        if ($added_on === '') { $added_on = date('Y-m-d'); }
        $cover = null;

        if ($title === '') {
            $error = 'Book title is required.';
        } else {
            if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $dir = lb_books_dir();
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $cover = 'b_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . '/' . $cover)) { $cover = null; }
                }
            }
            $st2 = db_prepare("INSERT INTO books (title, author, book_no, isbn, publisher, subject, department, rack_shelf, category, quantity, available, price, added_on, cover_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $st2->bind_param('sssssssssiidss', $title, $author, $book_no, $isbn, $publisher, $subject, $department, $rack_shelf, $category, $quantity, $available, $price, $added_on, $cover);
            $st2->execute();
            $message = 'Book added successfully!';
        }
    }

    if ($action === 'UpdateBook') {
        $book_id = (int) ($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $book_no = trim($_POST['book_no'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $rack_shelf = trim($_POST['rack_shelf'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 1);
        if ($quantity < 1) { $quantity = 1; }
        $available = (int) ($_POST['available'] ?? $quantity);
        if ($available > $quantity) { $available = $quantity; }
        $price = (float) ($_POST['price'] ?? 0);
        $added_on = trim($_POST['added_on'] ?? '');
        if ($added_on === '') { $added_on = null; }
        $cover = null;

        if ($title !== '') {
            if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $dir = lb_books_dir();
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $cover = 'b_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . '/' . $cover)) { $cover = null; }
                }
            }
            $fields = 'title=?, author=?, book_no=?, isbn=?, publisher=?, subject=?, department=?, rack_shelf=?, category=?, quantity=?, available=?, price=?, added_on=?';
            $vals = ['sssssssssiids', $title, $author, $book_no, $isbn, $publisher, $subject, $department, $rack_shelf, $category, $quantity, $available, $price, $added_on];
            if ($cover !== null) { $fields .= ', cover_image=?'; array_splice($vals, 1, 0, 's'); $vals[] = $cover; }
            $fields .= ' WHERE book_id=?';
            $st2 = db_prepare("UPDATE books SET $fields");
            $params = array_merge([$vals[0]], array_slice($vals, 1));
            $params[] = $book_id;
            $st2->bind_param(...$params);
            $st2->execute();
            $message = 'Book updated successfully!';
        } else {
            $error = 'Book title is required.';
        }
    }

    if ($action === 'DeleteBook') {
        $book_id = (int) ($_POST['book_id'] ?? 0);
        $st2 = db_prepare("DELETE FROM books WHERE book_id=?");
        $st2->bind_param('i', $book_id);
        $st2->execute();
        $message = 'Book deleted successfully!';
    }

    if ($action === 'DeleteBulkBooks') {
        $ids = $_POST['book_ids'] ?? [];
        $cnt = 0;
        $del = db_prepare("DELETE FROM books WHERE book_id=?");
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) { $del->bind_param('i', $id); $del->execute(); $cnt++; }
        }
        $message = $cnt . ' book(s) deleted successfully!';
    }

    if ($action === 'ImportBooks') {
        if (empty($_FILES['import_file']['name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a CSV file to import.';
        } else {
            $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
            $first = true;
            $header = [];
            $cnt = 0;
            $skip = 0;
            if ($handle) {
                while (($data = fgetcsv($handle)) !== false) {
                    $data = array_map('trim', $data);
                    if ($first) {
                        $first = false;
                        if (strtolower($data[0] ?? '') === 'title' || (stripos($data[0] ?? '', 'title') !== false)) {
                            $header = array_map('strtolower', $data);
                            continue;
                        } else {
                            $header = [];
                        }
                    }
                    if (count($header) > 0) {
                        $map = function ($name) use ($header, $data) {
                            $idx = array_search($name, $header);
                            return $idx !== false ? ($data[$idx] ?? '') : '';
                        };
                        $title = $map('title');
                        $author = $map('author');
                        $book_no = $map('book_no');
                        $isbn = $map('isbn');
                        $publisher = $map('publisher');
                        $subject = $map('subject');
                        $department = $map('department');
                        $rack = ($map('rack_shelf') !== '') ? $map('rack_shelf') : $map('rack');
                        $category = $map('category');
                        $quantity = (int) $map('quantity');
                        $price = (float) $map('price');
                        $added = $map('added_on');
                    } else {
                        $title = $data[0] ?? '';
                        $author = $data[1] ?? '';
                        $book_no = $data[2] ?? '';
                        $isbn = $data[3] ?? '';
                        $publisher = $data[4] ?? '';
                        $subject = $data[5] ?? '';
                        $department = $data[6] ?? '';
                        $rack = $data[7] ?? '';
                        $quantity = (int) ($data[8] ?? 1);
                        $price = (float) ($data[9] ?? 0);
                        $category = $data[10] ?? '';
                        $added = $data[11] ?? '';
                    }
                    if ($title === '') { $skip++; continue; }
                    if ($quantity < 1) { $quantity = 1; }
                    $available = $quantity;
                    if ($added === '' || strtotime($added) === false) { $added = date('Y-m-d'); }
                    $st2 = db_prepare("INSERT INTO books (title, author, book_no, isbn, publisher, subject, department, rack_shelf, category, quantity, available, price, added_on, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $st2->bind_param('sssssssssiids', $title, $author, $book_no, $isbn, $publisher, $subject, $department, $rack, $category, $quantity, $available, $price, $added);
                    $st2->execute();
                    $cnt++;
                }
                fclose($handle);
            }
            $message = "Imported $cnt book(s) successfully" . ($skip > 0 ? " ($skip blank rows skipped)" : '') . '!';
        }
    }
}

$books = [];
$sql = "SELECT b.*,
        (SELECT COUNT(*) FROM book_issues bi WHERE bi.book_id=b.book_id AND COALESCE(bi.status,'')='issued') AS issued
        FROM books b ORDER BY b.title ASC, b.book_id ASC";
$res = db_query($sql);
while ($row = $res->fetch_assoc()) {
    $row['available'] = (int) ($row['available'] ?? 0);
    $row['quantity'] = (int) ($row['quantity'] ?? 0);
    $row['issued'] = (int) ($row['issued'] ?? 0);
    $books[] = $row;
}

$stat = ['total' => 0, 'issued' => 0, 'available' => 0, 'new_month' => 0, 'lost' => 0, 'low' => 0];
$res = db_query("SELECT COUNT(*) AS c, COALESCE(SUM(available),0) AS avail FROM books WHERE status=1");
if ($res) { $row = $res->fetch_assoc(); $stat['total'] = (int) $row['c']; $stat['available'] = (int) $row['avail']; }
$res = db_query("SELECT COUNT(*) AS c FROM book_issues WHERE COALESCE(status,'')='issued'");
if ($res) { $stat['issued'] = (int) $res->fetch_assoc()['c']; }
$res = db_query("SELECT COUNT(*) AS c FROM books WHERE status=1 AND added_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
if ($res) { $stat['new_month'] = (int) $res->fetch_assoc()['c']; }
$res = db_query("SELECT COUNT(*) AS c FROM book_issues WHERE COALESCE(status,'') IN ('lost','damaged')");
if ($res) { $stat['lost'] = (int) $res->fetch_assoc()['c']; }
$res = db_query("SELECT COUNT(*) AS c FROM books WHERE status=1 AND available <= 5");
if ($res) { $stat['low'] = (int) $res->fetch_assoc()['c']; }

$deptOptions = [];
$res = db_query("SELECT DISTINCT department FROM books WHERE department IS NOT NULL AND department <> '' ORDER BY department");
while ($row = $res->fetch_assoc()) { $deptOptions[] = $row['department']; }
$subjectOptions = [];
$res = db_query("SELECT DISTINCT subject FROM books WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject");
while ($row = $res->fetch_assoc()) { $subjectOptions[] = $row['subject']; }
$rackOptions = [];
$res = db_query("SELECT DISTINCT rack_shelf FROM books WHERE rack_shelf IS NOT NULL AND rack_shelf <> '' ORDER BY rack_shelf");
while ($row = $res->fetch_assoc()) { $rackOptions[] = $row['rack_shelf']; }

$currency = get_setting('currency_symbol', 'Rs.');

include __DIR__ . '/includes/header.php';
?>
<style>
.lib-stat { border-radius: 10px; padding: 12px 14px 10px; background: #fff; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,.06); transition: transform .15s ease, box-shadow .15s ease; }
.lib-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,.1); }
.lib-stat .stat-top { display: flex; align-items: flex-start; justify-content: space-between; }
.lib-stat .num { font-size: 21px; font-weight: 800; color: #1a202c; line-height: 1; }
.lib-stat .lbl { font-size: 11px; color: #718096; font-weight: 600; margin-top: 4px; }
.lib-stat .stat-icon { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 15px; color: #fff; box-shadow: 0 3px 8px rgba(0,0,0,.15); }
.stat-bg-orange { background: #fff7ed !important; }
.stat-bg-blue { background: #ebf8ff !important; }
.stat-bg-purple { background: #f5f3ff !important; }
.stat-bg-green { background: #f0fff4 !important; }
.stat-bg-red { background: #fff5f5 !important; }
.stat-bg-gold { background: #fffbeb !important; }
.clr-orange { background: #ed8936; } .txt-orange { color: #ed8936; }
.clr-blue { background: #4299e1; } .txt-blue { color: #4299e1; }
.clr-purple { background: #8b5cf6; } .txt-purple { color: #8b5cf6; }
.clr-green { background: #48bb78; } .txt-green { color: #48bb78; }
.clr-red { background: #f56565; } .txt-red { color: #f56565; }
.clr-gold { background: #d69e2e; } .txt-gold { color: #d69e2e; }
.tbl-card { background: #fff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 18px; margin-bottom: 18px; }
.tbl-card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #edf2f7; flex-wrap: wrap; gap: 10px; }
.tbl-card-hdr h4 { margin: 0; color: #2d3748; font-size: 15px; font-weight: 600; }
.filter-row { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.filter-item { display: flex; flex-direction: column; gap: 4px; flex: 1 1 130px; min-width: 120px; }
.filter-item.search-item { flex: 1.7 1 200px; }
.filter-item label { font-size: 10.5px; font-weight: 700; color: #a0aec0; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 0; }
.filter-row .form-control { height: 34px; padding: 4px 10px; font-size: 12.5px; }
.search-input-wrap { position: relative; }
.search-input-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #a0aec0; font-size: 12px; pointer-events: none; }
.search-input-wrap input { padding-left: 28px; }
#bulkBar { display: none; align-items: center; gap: 10px; background: #ebf4ff; border: 1px solid #bee3f8; border-radius: 5px; padding: 8px 12px; margin-bottom: 12px; font-size: 12.5px; color: #2c5282; }
.tbl-card table thead th { background-color: #4a5568 !important; color: #fff !important; border: none !important; font-size: 11.5px; white-space: nowrap; }
.tbl-card table tbody td { font-size: 12.5px; vertical-align: middle; }
.badge-qty { background: #667eea; color: #fff; padding: 3px 9px; border-radius: 10px; font-size: 12px; }
.badge-avail { background: #48bb78; color: #fff; padding: 3px 9px; border-radius: 10px; font-size: 11px; }
.badge-issued { background: #ed8936; color: #fff; padding: 3px 9px; border-radius: 10px; font-size: 11px; }
.badge-zero { background: #e53e3e; color: #fff; padding: 3px 9px; border-radius: 10px; font-size: 11px; }
.book-thumb { width: 32px; height: 42px; object-fit: cover; border-radius: 2px; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
.book-thumb-ph { width: 32px; height: 42px; border-radius: 2px; background: #edf2f7; color: #a0aec0; display: flex; align-items: center; justify-content: center; font-size: 14px; }
.modal-header { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); border-radius: 4px 4px 0 0; }
.modal-header .modal-title { color: #fff; font-size: 15px; }
.modal-header .close { color: #fff; opacity: .8; }
.form-lbl { font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.empty { text-align: center; padding: 40px 20px; color: #9CA3AF; }
.empty i { font-size: 48px; display: block; margin-bottom: 10px; }
.hdr-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 4px; flex-wrap: wrap; gap: 10px; }
.hdr-row h3 { font-size: 18px; font-weight: 800; color: #111827; margin: 0; }
.barcode-strip { font-family: 'Libre Barcode 128', 'Courier New', monospace; font-size: 26px; letter-spacing: 2px; text-align: center; }
@media print { .no-print { display: none !important; } }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="hdr-row">
            <h3><i class="fa fa-book"></i> Book List <span style="font-size:14px; color:#6B7280;">(<?php echo $stat['total']; ?> books)</span></h3>
            <div class="no-print">
                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#addBookModal"><i class="fa fa-plus"></i> Add Book</button>
                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#importModal"><i class="fa fa-upload"></i> Import Excel</button>
                <a href="<?php echo BASE_URL; ?>list_books.php?action=export" class="btn btn-primary btn-sm" style="color:#fff;"><i class="fa fa-download"></i> Export</a>
                <button type="button" class="btn btn-default btn-sm" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-default btn-sm" onclick="printSelectedBarcodes('');"><i class="fa fa-barcode"></i> Barcode</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkDeleteSelected();"><i class="fa fa-trash"></i> Bulk Delete</button>
            </div>
        </div>

        <div class="row">
            <?php $cards = [
                ['bg' => 'stat-bg-orange', 'clr' => 'clr-orange', 'txt' => 'txt-orange', 'icon' => 'fa-book', 'num' => $stat['total'], 'lbl' => 'Total Books'],
                ['bg' => 'stat-bg-blue', 'clr' => 'clr-blue', 'txt' => 'txt-blue', 'icon' => 'fa-share', 'num' => $stat['issued'], 'lbl' => 'Issued'],
                ['bg' => 'stat-bg-green', 'clr' => 'clr-green', 'txt' => 'txt-green', 'icon' => 'fa-check-circle', 'num' => $stat['available'], 'lbl' => 'Available'],
                ['bg' => 'stat-bg-purple', 'clr' => 'clr-purple', 'txt' => 'txt-purple', 'icon' => 'fa-plus', 'num' => $stat['new_month'], 'lbl' => 'New This Month'],
                ['bg' => 'stat-bg-red', 'clr' => 'clr-red', 'txt' => 'txt-red', 'icon' => 'fa-exclamation-triangle', 'num' => $stat['lost'], 'lbl' => 'Lost / Damaged'],
                ['bg' => 'stat-bg-gold', 'clr' => 'clr-gold', 'txt' => 'txt-gold', 'icon' => 'fa-bar-chart', 'num' => $stat['low'], 'lbl' => 'Low Stock'],
            ]; ?>
            <?php foreach ($cards as $c): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 col-xs-6">
                    <div class="lib-stat <?php echo $c['bg']; ?>">
                        <div class="stat-top">
                            <div><div class="num"><?php echo (int) $c['num']; ?></div><div class="lbl"><?php echo $c['lbl']; ?></div></div>
                            <div class="stat-icon <?php echo $c['clr']; ?>"><i class="fa <?php echo $c['icon']; ?>"></i></div>
                        </div>
                        <a href="#booksCatalogCard" class="view-all <?php echo $c['txt']; ?>" style="display:inline-block; margin-top:7px; font-size:11px; font-weight:700; text-decoration:none;">View All <i class="fa fa-angle-right"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tbl-card" id="booksCatalogCard">
            <div class="tbl-card-hdr">
                <h4><i class="fa fa-list" style="color:#667eea;"></i> &nbsp;Books Catalog</h4>
            </div>

            <div class="filter-row no-print">
                <div class="filter-item search-item">
                    <label>Search Books</label>
                    <div class="search-input-wrap">
                        <i class="fa fa-search"></i>
                        <input type="text" id="tableSearch" class="form-control" placeholder="Type here to search...">
                    </div>
                </div>
                <div class="filter-item">
                    <label>Department</label>
                    <select id="departmentFilter" class="form-control"><option value="">All Departments</option>
                        <?php foreach ($deptOptions as $d): ?><option value="<?php echo e($d); ?>"><?php echo e($d); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Subject</label>
                    <select id="subjectFilter" class="form-control"><option value="">All Subjects</option>
                        <?php foreach ($subjectOptions as $s): ?><option value="<?php echo e($s); ?>"><?php echo e($s); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Rack / Shelf</label>
                    <select id="rackFilter" class="form-control"><option value="">All Racks</option>
                        <?php foreach ($rackOptions as $r): ?><option value="<?php echo e($r); ?>"><?php echo e($r); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="issued">Issued</option>
                    </select>
                </div>
                <div class="filter-item" style="flex-direction:row; gap:8px; align-items:flex-end;">
                    <button type="button" id="resetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</button>
                </div>
            </div>

            <div id="bulkBar" class="no-print">
                <span><strong id="bulkCount">0</strong> book(s) selected</span>
                <button type="button" class="btn btn-danger btn-xs" onclick="bulkDeleteSelected();"><i class="fa fa-trash"></i> Bulk Delete</button>
                <button type="button" class="btn btn-default btn-xs" onclick="printSelectedBarcodes('');"><i class="fa fa-barcode"></i> Print Barcode</button>
            </div>

            <form id="bulkForm" method="post" action="list_books.php" style="display:none;">
                <input type="hidden" name="action" value="DeleteBulkBooks">
                <div id="bulkIds"></div>
            </form>

            <?php if (count($books) === 0): ?>
                <div class="empty"><i class="fa fa-book"></i> No books found. Click "Add Book" to create your first record.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table table-striped table-bordered table-hover" id="booksTable" style="width:100%; margin:0;">
                        <thead>
                            <tr>
                                <th style="width:26px;"><input type="checkbox" id="checkAll"></th>
                                <th>#</th>
                                <th>Cover</th>
                                <th>Book Title</th>
                                <th>Book No.</th>
                                <th>ISBN</th>
                                <th>Publisher</th>
                                <th>Author</th>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Rack/Shelf</th>
                                <th>Total Qty</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Added On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $idx => $b):
                                $cover = ($b['cover_image'] ?? '') !== '' ? BASE_URL . 'uploads/books/' . $b['cover_image'] : '';
                                $status = $b['available'] > 0 ? 'available' : 'issued';
                                $statusText = $b['available'] > 0 ? 'Available' : 'Issued';
                            ?>
                                <tr data-key="<?php echo e(strtolower(($b['title'] ?? '') . ' ' . ($b['author'] ?? '') . ' ' . ($b['book_no'] ?? '') . ' ' . ($b['isbn'] ?? '') . ' ' . ($b['publisher'] ?? ''))); ?>"
                                    data-dept="<?php echo e($b['department'] ?? ''); ?>" data-subject="<?php echo e($b['subject'] ?? ''); ?>"
                                    data-rack="<?php echo e($b['rack_shelf'] ?? ''); ?>" data-status="<?php echo $status; ?>">
                                    <td><input type="checkbox" class="row-check" value="<?php echo $b['book_id']; ?>"></td>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td>
                                        <?php if ($cover): ?>
                                            <img src="<?php echo e($cover); ?>" class="book-thumb" alt="">
                                        <?php else: ?>
                                            <div class="book-thumb-ph"><i class="fa fa-book"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo e($b['title']); ?></strong></td>
                                    <td><?php echo e($b['book_no'] ?? '-'); ?></td>
                                    <td><?php echo e($b['isbn'] ?? '-'); ?></td>
                                    <td><?php echo e($b['publisher'] ?? '-'); ?></td>
                                    <td><?php echo e($b['author'] ?? '-'); ?></td>
                                    <td><?php echo e($b['subject'] ?? '-'); ?></td>
                                    <td><?php echo e($b['department'] ?? '-'); ?></td>
                                    <td><?php echo e($b['rack_shelf'] ?? '-'); ?></td>
                                    <td><span class="badge-qty"><?php echo $b['quantity']; ?></span></td>
                                    <td><span class="badge-avail"><?php echo $b['available']; ?></span></td>
                                    <td><span class="<?php echo $b['available'] > 0 ? 'badge-avail' : 'badge-zero'; ?>"><?php echo $statusText; ?></span></td>
                                    <td><?php echo $currency . ' ' . number_format((float) $b['price'], 0); ?></td>
                                    <td><?php echo $b['added_on'] ? date('d M Y', strtotime($b['added_on'])) : '-'; ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" class="btn btn-warning btn-xs edit-b" style="color:#fff;"
                                            data-id="<?php echo $b['book_id']; ?>"
                                            data-title="<?php echo e($b['title']); ?>"
                                            data-author="<?php echo e($b['author']); ?>"
                                            data-book_no="<?php echo e($b['book_no']); ?>"
                                            data-isbn="<?php echo e($b['isbn']); ?>"
                                            data-publisher="<?php echo e($b['publisher']); ?>"
                                            data-subject="<?php echo e($b['subject']); ?>"
                                            data-department="<?php echo e($b['department']); ?>"
                                            data-rack="<?php echo e($b['rack_shelf']); ?>"
                                            data-category="<?php echo e($b['category']); ?>"
                                            data-quantity="<?php echo $b['quantity']; ?>"
                                            data-available="<?php echo $b['available']; ?>"
                                            data-price="<?php echo $b['price']; ?>"
                                            data-added="<?php echo $b['added_on'] ?? ''; ?>"><i class="fa fa-pencil"></i></button>
                                        <form method="post" action="list_books.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this book?');">
                                            <input type="hidden" name="action" value="DeleteBook">
                                            <input type="hidden" name="book_id" value="<?php echo $b['book_id']; ?>">
                                            <button class="btn btn-danger btn-xs"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addBookModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-plus"></i> &nbsp;Add Book</h4>
            </div>
            <form method="post" action="list_books.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="AddBook">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label class="form-lbl">Book Title <span style="color:red;">*</span></label><input type="text" name="title" class="form-control" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Book No.</label><input type="text" name="book_no" class="form-control"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">ISBN</label><input type="text" name="isbn" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Author</label><input type="text" name="author" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Publisher</label><input type="text" name="publisher" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Subject</label><input type="text" name="subject" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Department</label><input type="text" name="department" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Rack / Shelf</label><input type="text" name="rack_shelf" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Category</label><input type="text" name="category" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Quantity</label><input type="number" name="quantity" class="form-control" value="1" min="1"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Available Copies</label><input type="number" name="available" class="form-control" value="1" min="0"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Price</label><input type="number" step="0.01" name="price" class="form-control" value="0"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Added On</label><input type="date" name="added_on" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div></div>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Book Cover</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-check"></i> Save Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editBookModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-pencil"></i> &nbsp;Edit Book</h4>
            </div>
            <form method="post" action="list_books.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="UpdateBook">
                    <input type="hidden" name="book_id" id="ubId">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label class="form-lbl">Book Title <span style="color:red;">*</span></label><input type="text" name="title" id="ubTitle" class="form-control" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Book No.</label><input type="text" name="book_no" id="ubBookNo" class="form-control"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">ISBN</label><input type="text" name="isbn" id="ubIsbn" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Author</label><input type="text" name="author" id="ubAuthor" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Publisher</label><input type="text" name="publisher" id="ubPublisher" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Subject</label><input type="text" name="subject" id="ubSubject" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Department</label><input type="text" name="department" id="ubDepartment" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Rack / Shelf</label><input type="text" name="rack_shelf" id="ubRack" class="form-control"></div></div>
                        <div class="col-md-4"><div class="form-group"><label class="form-lbl">Category</label><input type="text" name="category" id="ubCategory" class="form-control"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Quantity</label><input type="number" name="quantity" id="ubQuantity" class="form-control" min="1"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Available Copies</label><input type="number" name="available" id="ubAvailable" class="form-control" min="0"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Price</label><input type="number" step="0.01" name="price" id="ubPrice" class="form-control"></div></div>
                        <div class="col-md-3"><div class="form-group"><label class="form-lbl">Added On</label><input type="date" name="added_on" id="ubAdded" class="form-control"></div></div>
                    </div>
                    <div class="form-group">
                        <label class="form-lbl">Book Cover (leave blank to keep current)</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="color:#fff;"><i class="fa fa-check"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-upload"></i> &nbsp;Import Books from CSV</h4>
            </div>
            <form method="post" action="list_books.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="ImportBooks">
                    <div class="form-group">
                        <label class="form-lbl">CSV File <span style="color:red;">*</span></label>
                        <input type="file" name="import_file" class="form-control" accept=".csv,text/csv" required>
                    </div>
                    <p style="font-size:12px; color:#718096;">Optional header: title, author, book_no, isbn, publisher, subject, department, rack_shelf, category, quantity, price, added_on. Without a header, columns are read in that order.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" style="color:#fff;"><i class="fa fa-check"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function applyFilter(){
    var q = document.getElementById('tableSearch').value.trim().toLowerCase();
    var dept = document.getElementById('departmentFilter').value;
    var subject = document.getElementById('subjectFilter').value;
    var rack = document.getElementById('rackFilter').value;
    var status = document.getElementById('statusFilter').value;
    document.querySelectorAll('#booksTable tbody tr').forEach(function(tr){
        var show = true;
        if (q !== '' && (tr.getAttribute('data-key') || '').indexOf(q) === -1) show = false;
        if (show && dept !== '' && tr.getAttribute('data-dept') !== dept) show = false;
        if (show && subject !== '' && tr.getAttribute('data-subject') !== subject) show = false;
        if (show && rack !== '' && tr.getAttribute('data-rack') !== rack) show = false;
        if (show && status !== '' && tr.getAttribute('data-status') !== status) show = false;
        tr.style.display = show ? '' : 'none';
    });
}
document.getElementById('tableSearch').addEventListener('keyup', applyFilter);
document.getElementById('departmentFilter').addEventListener('change', applyFilter);
document.getElementById('subjectFilter').addEventListener('change', applyFilter);
document.getElementById('rackFilter').addEventListener('change', applyFilter);
document.getElementById('statusFilter').addEventListener('change', applyFilter);
document.getElementById('resetFilters').addEventListener('click', function(){
    document.getElementById('tableSearch').value = '';
    document.getElementById('departmentFilter').value = '';
    document.getElementById('subjectFilter').value = '';
    document.getElementById('rackFilter').value = '';
    document.getElementById('statusFilter').value = '';
    applyFilter();
});
function selectedVector(){
    var out = [];
    document.querySelectorAll('#booksTable tbody tr .row-check').forEach(function(cb){
        if (cb.checked && cb.closest('tr').style.display !== 'none') out.push(cb.value);
    });
    return out;
}
function updateBulkBar(){
    var v = selectedVector();
    document.getElementById('bulkCount').textContent = v.length;
    document.getElementById('bulkBar').style.display = v.length > 0 ? 'flex' : 'none';
}
document.getElementById('checkAll').addEventListener('change', function(){
    document.querySelectorAll('#booksTable tbody tr').forEach(function(tr){
        if (tr.style.display !== 'none') { tr.querySelector('.row-check').checked = this.checked; }
    }, this);
    updateBulkBar();
});
document.querySelectorAll('.row-check').forEach(function(cb){
    cb.addEventListener('change', updateBulkBar);
});
function bulkDeleteSelected(){
    var v = selectedVector();
    if (v.length === 0) { alert('No books selected.'); return; }
    if (!confirm('Delete ' + v.length + ' selected book(s)?')) return;
    var box = document.getElementById('bulkIds');
    box.innerHTML = '';
    v.forEach(function(id){
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'book_ids[]'; inp.value = id;
        box.appendChild(inp);
    });
    document.getElementById('bulkForm').submit();
}
function printSelectedBarcodes(mode){
    var v = selectedVector();
    var pick = v.length > 0
        ? (mode === 'all' ? [] : v)
        : Array.from(document.querySelectorAll('#booksTable tbody tr')).map(function(tr){ return tr.querySelector('.row-check').value; });
    if (mode === 'all' && v.length === 0) {
        pick = Array.from(document.querySelectorAll('#booksTable tbody tr .row-check')).map(function(cb){ return cb.value; });
    }
    var rows = document.querySelectorAll('#booksTable tbody tr');
    var html = '<div style="font-family:Arial,sans-serif;">';
    rows.forEach(function(tr){
        var cb = tr.querySelector('.row-check');
        if (pick.indexOf(cb.value) === -1) return;
        var bno = tr.cells[4].textContent.trim();
        var title = tr.cells[3].textContent.trim();
        var author = tr.cells[7].textContent.trim();
        html += '<div style="border:1px dashed #999; padding:8px 12px; margin:6px; display:inline-block; min-width:220px; text-align:center;">';
        html += '<div style="font-size:11px; font-weight:700; letter-spacing:2px;">' + (bno !== '-' ? bno : 'NO-BARCODE') + '</div>';
        html += '<div class="barcode-strip" style="font-family:\'Courier New\',monospace; font-size:28px; letter-spacing:3px; margin:4px 0;">' + (bno !== '-' ? bno.replace(/[^a-zA-Z0-9]/g, '') : '') + '</div>';
        html += '<div style="font-size:12px; font-weight:700;">' + title + '</div>';
        html += '<div style="font-size:10px; color:#555;">' + author + '</div>';
        html += '</div>';
    });
    html += '</div>';
    var w = window.open('', '_blank');
    w.document.write('<html><head><title>Barcode Label Strip</title></head><body>' + html + '</body></html>');
    w.document.close();
    w.print();
}
document.querySelectorAll('.edit-b').forEach(function(btn){
    btn.addEventListener('click', function(){
        var d = this.dataset;
        document.getElementById('ubId').value = d.id;
        document.getElementById('ubTitle').value = d.title;
        document.getElementById('ubBookNo').value = d.bookNo;
        document.getElementById('ubIsbn').value = d.isbn;
        document.getElementById('ubAuthor').value = d.author;
        document.getElementById('ubPublisher').value = d.publisher;
        document.getElementById('ubSubject').value = d.subject;
        document.getElementById('ubDepartment').value = d.department;
        document.getElementById('ubRack').value = d.rack;
        document.getElementById('ubCategory').value = d.category;
        document.getElementById('ubQuantity').value = d.quantity;
        document.getElementById('ubAvailable').value = d.available;
        document.getElementById('ubPrice').value = d.price;
        document.getElementById('ubAdded').value = d.added || '';
        jQuery('#editBookModal').modal('show');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>