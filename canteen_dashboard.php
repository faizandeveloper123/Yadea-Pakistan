<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Structured Curriculum Builder';

$message = '';
$error = '';

// CREATE TABLE IF NOT EXISTS pos_products, pos_invoices, pos_invoice_items
db_query("CREATE TABLE IF NOT EXISTS pos_products (
    product_id int(11) NOT NULL AUTO_INCREMENT,
    product_name varchar(120) NOT NULL,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    stock int(11) NOT NULL DEFAULT 0,
    status tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

db_query("CREATE TABLE IF NOT EXISTS pos_invoices (
    invoice_id int(11) NOT NULL AUTO_INCREMENT,
    invoice_no varchar(40) NOT NULL,
    customer_name varchar(120) DEFAULT NULL,
    total_amount decimal(12,2) NOT NULL DEFAULT 0.00,
    paid_amount decimal(12,2) NOT NULL DEFAULT 0.00,
    payment_method varchar(20) DEFAULT 'cash',
    status varchar(20) NOT NULL DEFAULT 'unpaid',
    user_id int(11) DEFAULT NULL,
    created_at timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

db_query("CREATE TABLE IF NOT EXISTS pos_invoice_items (
    item_id int(11) NOT NULL AUTO_INCREMENT,
    invoice_id int(11) NOT NULL,
    product_id int(11) DEFAULT NULL,
    product_name varchar(120) NOT NULL,
    qty int(11) NOT NULL DEFAULT 1,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (item_id),
    KEY invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddProduct') {
        $name = trim($_POST['product_name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        if ($name === '') {
            $error = 'Product name is required.';
        } else {
            $st2 = db_prepare("INSERT INTO pos_products (product_name, price, stock) VALUES (?, ?, ?)");
            $st2->bind_param('sdi', $name, $price, $stock);
            $st2->execute();
            $message = "Product '$name' added!";
        }
    }

    if ($action === 'CompleteSale') {
        $customer = trim($_POST['customer_name'] ?? '');
        $items_raw = $_POST['items'] ?? [];
        $total = 0.0;
        $lines = [];
        if (is_array($items_raw)) {
            foreach ($items_raw as $line) {
                $pid = (int) ($line['product_id'] ?? 0);
                $qty = max(1, (int) ($line['qty'] ?? 1));
                if ($pid <= 0) continue;
                $prod = db_query("SELECT * FROM pos_products WHERE product_id=$pid")->fetch_assoc();
                if (!$prod) continue;
                $lt = $prod['price'] * $qty;
                $total += $lt;
                $lines[] = ['pid' => $pid, 'name' => $prod['product_name'], 'qty' => $qty, 'price' => (float) $prod['price'], 'lt' => $lt];
            }
        }
        if (count($lines) === 0) {
            $error = 'Sale ke liye kam se kam ek item chahiye.';
        } else {
            $inv_no = 'POS-' . date('YmdHis') . '-' . rand(100, 999);
            $method = trim($_POST['payment_method'] ?? 'cash');
            $st2 = db_prepare("INSERT INTO pos_invoices (invoice_no, customer_name, total_amount, payment_method, status, user_id) VALUES (?, ?, ?, ?, 'paid', ?)");
            $st2->bind_param('ssdsi', $inv_no, $customer, $total, $method, $_SESSION['user_id']);
            $st2->execute();
            $invoice_id = $st2->insert_id;
            foreach ($lines as $ln) {
                $st3 = db_prepare("INSERT INTO pos_invoice_items (invoice_id, product_id, product_name, qty, price) VALUES (?, ?, ?, ?, ?)");
                $st3->bind_param('iisid', $invoice_id, $ln['pid'], $ln['name'], $ln['qty'], $ln['price']);
                $st3->execute();
                db_query("UPDATE pos_products SET stock = stock - " . $ln['qty'] . " WHERE product_id={$ln['pid']}");
            }
            $message = "Sale completed! Invoice: $inv_no (Total: " . number_format($total, 2) . ")";
        }
    }
}

$products = [];
$res = db_query("SELECT * FROM pos_products ORDER BY product_name");
while ($row = $res->fetch_assoc()) { $products[] = $row; }

$today_invoices = [];
$res = db_query("SELECT * FROM pos_invoices WHERE DATE(created_at)=CURDATE() ORDER BY invoice_id DESC");
while ($row = $res->fetch_assoc()) { $today_invoices[] = $row; }

$today_sales = (float) (db_query("SELECT COALESCE(SUM(total_amount),0) t FROM pos_invoices WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['t'] ?? 0);

include __DIR__ . '/includes/header.php';
?>
<style>
.pos-product { border:2px solid #E5E7EB; border-radius:12px; padding:12px; text-align:center; cursor:pointer; background:#fff; user-select:none; transition:all .15s; }
.pos-product:hover, .pos-product.active { border-color:#FF7A1B; background:#FFF8F2; }
.pos-product .nm { font-weight:800; color:#111827; font-size:13px; }
.pos-product .pr { color:#FF7A1B; font-weight:800; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px;">
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-shopping-cart"></i> POS Dashboard</h3>
        </div>

        <div class="row" style="margin-bottom:14px;">
            <div class="col-md-3">
                <div class="dashboard-card" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;">
                    <div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Today's Sales</div>
                    <div style="font-size:22px; font-weight:800; color:#16A34A;"><?php echo number_format($today_sales, 2); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;">
                    <div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Today's Invoices</div>
                    <div style="font-size:22px; font-weight:800;"><?php echo count($today_invoices); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px;">
                    <div style="color:#6B7280; font-size:12px; text-transform:uppercase;">Products</div>
                    <div style="font-size:22px; font-weight:800;"><?php echo count($products); ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;">Products (click to add to cart)</h4>
                    <div class="row">
                        <?php if (count($products) === 0): ?>
                            <div style="color:#6B7280; padding:15px;">Koi product nahi. Niche form se add karein.</div>
                        <?php endif; ?>
                        <?php foreach ($products as $pr): ?>
                            <div class="col-md-3 col-sm-4" style="margin-bottom:10px;">
                                <div class="pos-product" onclick="addToCart(<?php echo $pr['product_id']; ?>,'<?php echo e(addslashes($pr['product_name'])); ?>',<?php echo $pr['price']; ?>)">
                                    <div class="nm"><?php echo e($pr['product_name']); ?></div>
                                    <div class="pr"><?php echo get_setting('currency_symbol') ?: 'Rs.'; ?> <?php echo number_format($pr['price'], 2); ?></div>
                                    <div style="font-size:11px; color:#6B7280;">Stock: <?php echo $pr['stock']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <form method="post" action="canteen_dashboard.php" class="row" style="margin:0;">
                        <input type="hidden" name="action" value="AddProduct">
                        <div class="form-group col-md-4"><input type="text" name="product_name" class="form-control" placeholder="Product name" required></div>
                        <div class="form-group col-md-2"><input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required></div>
                        <div class="form-group col-md-2"><input type="number" name="stock" class="form-control" placeholder="Stock" value="0"></div>
                        <div class="form-group col-md-2"><button class="btn btn-success" style="width:100%;">Add Product</button></div>
                    </form>
                </div>

                <div style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;">Today's Invoices</h4>
                    <table class="table table-bordered" style="width:100%; background:#fff; margin-bottom:0;">
                        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Method</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php if (count($today_invoices) === 0): ?>
                                <tr><td colspan="5" style="text-align:center; color:#6B7280; padding:20px;">Aaj koi sale nahi hui.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($today_invoices as $inv): ?>
                                <tr>
                                    <td><strong><?php echo e($inv['invoice_no']); ?></strong></td>
                                    <td><?php echo e($inv['customer_name'] ?: '-'); ?></td>
                                    <td style="color:#16A34A; font-weight:700;"><?php echo number_format($inv['total_amount'], 2); ?></td>
                                    <td><?php echo e(ucfirst($inv['payment_method'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($inv['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-5">
                <form method="post" action="canteen_dashboard.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; position:sticky; top:80px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;">Current Sale</h4>
                    <input type="hidden" name="action" value="CompleteSale">
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Walk-In">
                    </div>
                    <table class="table table-bordered cart-table" style="width:100%; background:#fff; margin-bottom:14px;">
                        <thead><tr><th>Item</th><th>Qty</th><th>Amount</th><th></th></tr></thead>
                        <tbody></tbody>
                    </table>
                    <div style="display:flex; gap:12px; align-items:center; margin-bottom:14px;">
                        <strong>Total:</strong>
                        <span class="cart-total" style="font-size:20px; font-weight:800; color:#FF7A1B;">0.00</span>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;"><i class="fa fa-check-circle"></i> Complete Sale</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
var cart = {};
function addToCart(id, name, price) {
    if (cart[id]) cart[id].qty++;
    else cart[id] = { name: name, price: price, qty: 1 };
    renderCart();
}
function changeQty(id, d) {
    if (!cart[id]) return;
    cart[id].qty += d;
    if (cart[id].qty < 1) delete cart[id];
    renderCart();
}
function removeItem(id) { delete cart[id]; renderCart(); }
function renderCart() {
    var tb = document.querySelector('.cart-table tbody');
    var total = 0;
    tb.innerHTML = '';
    Object.keys(cart).forEach(function(id) {
        var it = cart[id];
        total += it.price * it.qty;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + it.name + '<input type="hidden" name="items[' + id + '][product_id]" value="' + id + '"></td>' +
            '<td style="white-space:nowrap;"><button type="button" onclick="changeQty(' + id + ',-1)" class="btn btn-default btn-xs">&minus;</button> <span>' + it.qty + '</span> <button type="button" onclick="changeQty(' + id + ',1)" class="btn btn-default btn-xs">+</button>' +
            '<input type="hidden" name="items[' + id + '][qty]" value="' + it.qty + '"></td>' +
            '<td>' + (it.price * it.qty).toFixed(2) + '</td>' +
            '<td><button type="button" onclick="removeItem(' + id + ')" class="btn btn-danger btn-xs"><i class="fa fa-times"></i></button></td>';
        tb.appendChild(tr);
    });
    document.querySelector('.cart-total').textContent = total.toFixed(2);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>