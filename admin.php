<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // ✅ عرض الأخطاء للتطوير

require 'config.php';

// 🔐 التحقق من الدخول
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$message = "";
$message_type = "";
$data = getProducts();

// 🐛 دالة مساعدة لعرض تفاصيل الخطأ
function debugSave($success, $operation, $extra = []) {
    if (!$success) {
        error_log("[SAVE DEBUG] $operation failed - " . json_encode($extra));
    }
}

// ➕ إضافة منتج جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    
    // عرض البيانات المستلمة للتشخيص (احذف هذا الجزء بعد التأكد)
    // echo "<pre>"; print_r($_POST); print_r($_FILES); echo "</pre>"; exit;
    
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    
    if (!$name || $price <= 0) {
        $message = "⚠️ الرجاء إدخال اسم المنتج وسعر صحيح";
        $message_type = "warning";
    } else {
        $image_path = uploadProductImage($_FILES['image'] ?? null);
        
        $new_product = [
            'id' => ++$data['last_id'],
            'name' => $name, // سيتم تطبيق htmlspecialchars عند العرض
            'price' => $price,
            'description' => $desc,
            'image' => $image_path ?: 'products/no-image.png',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $data['products'][] = $new_product;
        
        // 🧪 اختبار الحفظ
        $saved = saveProducts($data);
        debugSave($saved, 'add_product', ['product' => $new_product]);
        
        if ($saved) {
            $message = "✅ تمت إضافة المنتج بنجاح";
            $message_type = "success";
            // إعادة تحميل الصفحة لمنع إعادة الإرسال
            header("Location: admin.php?msg=" . urlencode($message) . "&type=" . $message_type);
            exit;
        } else {
            $message = "❌ فشل في حفظ البيانات - تحقق من error_log";
            $message_type = "danger";
        }
    }
}

// ✏️ تعديل منتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $desc = trim($_POST['description']);
    $current_image = $_POST['current_image'] ?? '';
    
    if (!$name || $price <= 0) {
        $message = "⚠️ بيانات غير صحيحة";
        $message_type = "warning";
    } else {
        $image_path = $current_image;
        
        if (!empty($_FILES['image']['name'])) {
            $new_image = uploadProductImage($_FILES['image']);
            if ($new_image) {
                deleteProductImage($current_image);
                $image_path = $new_image;
            }
        }
        
        $updated = false;
        foreach ($data['products'] as &$prod) {
            if ($prod['id'] === $id) {
                $prod['name'] = $name;
                $prod['price'] = $price;
                $prod['description'] = $desc;
                $prod['image'] = $image_path;
                $prod['updated_at'] = date('Y-m-d H:i:s');
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            $saved = saveProducts($data);
            debugSave($saved, 'edit_product', ['id' => $id]);
            
            if ($saved) {
                $message = "✅ تم تحديث المنتج بنجاح";
                $message_type = "success";
                header("Location: admin.php?msg=" . urlencode($message) . "&type=" . $message_type);
                exit;
            } else {
                $message = "❌ فشل في حفظ التعديلات";
                $message_type = "danger";
            }
        }
    }
}

// 🗑️ حذف منتج
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    foreach ($data['products'] as $key => $prod) {
        if ($prod['id'] === $id) {
            deleteProductImage($prod['image']);
            unset($data['products'][$key]);
            $data['products'] = array_values($data['products']);
            break;
        }
    }
    
    if (saveProducts($data)) {
        header("Location: admin.php?msg=" . urlencode("✅ تم الحذف") . "&type=success");
        exit;
    }
}

// عرض رسالة
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

// المنتج الجاري تعديله
$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($data['products'] as $prod) {
        if ($prod['id'] == $_GET['edit']) {
            $edit_product = $prod;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - <?php echo COMPANY_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #1e3c72, #2a5298); min-height: 100vh; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); margin: 5px 0; border-radius: 10px; }
        .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: white; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        .product-img { width: 70px; height: 70px; object-fit: cover; border-radius: 10px; background: #eee; }
        .btn-action { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .preview-img { max-width: 100%; max-height: 150px; border-radius: 10px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3">
            <h5 class="text-center py-3">🎛️ التحكم</h5>
            <nav class="nav flex-column">
                <a class="nav-link active" href="admin.php"><i class="bi bi-grid"></i> المنتجات</a>
                <a class="nav-link text-danger" href="admin_logout.php"><i class="bi bi-box-arrow-right"></i> خروج</a>
            </nav>
        </div>
        
        <div class="col-md-10 p-4">
            <h4>📦 إدارة المنتجات</h4>
            
            <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- نموذج الإضافة/التعديل -->
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">
                    <?php echo $edit_product ? '✏️ تعديل' : '➕ إضافة'; ?> منتج
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?php if($edit_product): ?>
                            <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_product['image']); ?>">
                        <?php endif; ?>
                        
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">اسم المنتج *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">السعر (د.ل) *</label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($edit_product['price'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">صورة المنتج</label>
                                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                <?php if(!empty($edit_product['image']) && file_exists($edit_product['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_product['image']); ?>" class="preview-img" id="currentPreview">
                                <?php endif; ?>
                                <img src="" class="preview-img d-none" id="newPreview">
                            </div>
                            <div class="col-12">
                                <label class="form-label">وصف المنتج</label>
                                <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" 
                                class="btn btn-success mt-3">
                            <i class="bi bi-save"></i> <?php echo $edit_product ? 'تحديث' : 'إضافة'; ?>
                        </button>
                        <?php if($edit_product): ?>
                            <a href="admin.php" class="btn btn-secondary mt-3">إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- جدول المنتجات -->
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    المنتجات (<?php echo count($data['products']); ?>)
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>صورة</th><th>المنتج</th><th>السعر</th><th>الوصف</th><th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['products'] as $p): ?>
                            <tr>
                                <td>
                                    <?php if($p['image'] && file_exists($p['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" class="product-img">
                                    <?php else: ?>
                                        <div class="product-img d-flex align-items-center justify-content-center">📦</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong><br><small class="text-muted">ID: #<?php echo $p['id']; ?></small></td>
                                <td class="text-success fw-bold"><?php echo number_format($p['price'], 2); ?> د.ل</td>
                                <td class="text-muted small"><?php echo mb_substr(htmlspecialchars($p['description']), 0, 30) . (mb_strlen($p['description']) > 30 ? '...' : ''); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary btn-action"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger btn-action" onclick="return confirm('حذف؟')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($data['products'])): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">لا توجد منتجات</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input) {
    const preview = document.getElementById('newPreview');
    const current = document.getElementById('currentPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if(current) current.classList.add('d-none');
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>