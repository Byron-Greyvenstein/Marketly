<?php
/* ============================================================
   selling.php
   ------------------------------------------------------------
   Lets a logged-in user list an item for sale. The form
   collects title, category, description, price, condition,
   location, and up to 4 images (1 required, 3 optional).

   How this file works:
   1. Require the user to be logged in.
   2. If form submitted (POST):
      a. Validate text fields.
      b. Validate and save the uploaded image files.
      c. Insert the new product row into the database.
   3. Show the form (empty or with errors).
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$errors  = [];
$success = null;

// Pre-declare so the form can re-fill fields after a failed submission.
$title = $category = $description = $price = $item_condition = $location = '';


/* ------------------------------------------------------------
   FORM HANDLING
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Read text fields ----
    $title          = sanitize($_POST['item_title']     ?? '');
    $category       = sanitize($_POST['category']       ?? '');
    $description    = sanitize($_POST['description']    ?? '');
    $price          = $_POST['price']                   ?? '';   // validated below
    $item_condition = sanitize($_POST['item_condition'] ?? '');
    $location       = sanitize($_POST['location']       ?? '');
    $token          = $_POST['csrf_token']              ?? '';


    // ---- Security & basic checks ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please reload the page.';
    }

    if ($title === '' || $category === '' || $description === ''
        || $item_condition === '' || $location === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    // Validate price — must be a number, 0 or higher.
    $priceFloat = filter_var($price, FILTER_VALIDATE_FLOAT);
    if ($priceFloat === false || $priceFloat < 0) {
        $errors[] = 'Please enter a valid price.';
    }


    /* ------------------------------------------------------------
       IMAGE UPLOAD HANDLING
       ------------------------------------------------------------
       The form sends an array of files under the name "images[]".
       PHP exposes them as $_FILES['images'] with parallel arrays
       for name, type, tmp_name, error, and size.

       We expect 1–4 images. Image 1 is required.
       ------------------------------------------------------------ */

    // savedFilenames will hold the final filenames stored in the DB.
    $savedFilenames = [null, null, null, null];

    // Allowed image types and their file extensions.
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB per file

    // The folder where files will be saved.
    $uploadDir = __DIR__ . '/uploads/';

    // Check that at least image 1 was uploaded.
    // ($_FILES['images']['error'][0] === 0 means "uploaded successfully")
    if (empty($_FILES['images']['name'][0])
        || $_FILES['images']['error'][0] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload at least one image of your item.';
    }

    // Only attempt to process files if there were no other errors so far.
    if (empty($errors)) {

        // Loop through all 4 image slots.
        for ($i = 0; $i < 4; $i++) {

            // If this slot is empty, skip it (slots 2–4 are optional).
            if (empty($_FILES['images']['name'][$i])
                || $_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Did the upload itself succeed?
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = 'Image ' . ($i + 1) . ' failed to upload.';
                continue;
            }

            // Check file size.
            if ($_FILES['images']['size'][$i] > $maxFileSize) {
                $errors[] = 'Image ' . ($i + 1) . ' is larger than 5 MB.';
                continue;
            }

            // Check the file is genuinely an image we accept.
            // We don't trust the browser-supplied "type" — we use
            // PHP's own mime detection on the actual file contents.
            $tmpPath  = $_FILES['images']['tmp_name'][$i];
            $mimeType = mime_content_type($tmpPath);

            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Image ' . ($i + 1) . ' must be JPG, PNG, or WEBP.';
                continue;
            }

            // Build a safe, unique filename so two uploads can't clash
            // and the user can't choose a malicious filename.
            //   Example: prod_a8f3c2b4e1d6_1.jpg
            $extension = $allowedTypes[$mimeType];
            $uniqueId  = bin2hex(random_bytes(6));    // 12 random hex chars
            $newName   = 'prod_' . $uniqueId . '_' . ($i + 1) . '.' . $extension;

            // Move the file from PHP's temp storage to /uploads/.
            // move_uploaded_file is the secure way to handle uploads.
            if (move_uploaded_file($tmpPath, $uploadDir . $newName)) {
                $savedFilenames[$i] = $newName;
            } else {
                $errors[] = 'Could not save image ' . ($i + 1) . '. Please try again.';
            }
        }

        // Final safety check: image 1 must have ended up saved.
        if (empty($savedFilenames[0])) {
            $errors[] = 'At least one image is required.';
        }
    }


    /* ------------------------------------------------------------
       SAVE THE PRODUCT
       ------------------------------------------------------------ */
    if (empty($errors)) {

        $sellerId = current_user()['id'];
        $status   = 'active';

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO products
                (seller_id, name, description, price, category,
                 item_condition, location, status,
                 image_path_1, image_path_2, image_path_3, image_path_4)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        // Type string: i s s d s s s s s s s s
        //   i = seller_id (int)
        //   s = name, description (strings)
        //   d = price (double/decimal)
        //   s = category, condition, location, status, 4 image paths
        mysqli_stmt_bind_param(
            $stmt,
            'issdssssssss',
            $sellerId, $title, $description, $priceFloat, $category,
            $item_condition, $location, $status,
            $savedFilenames[0], $savedFilenames[1],
            $savedFilenames[2], $savedFilenames[3]
        );

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Your item has been listed successfully!';
            // Clear the form so the user can list another item.
            $title = $category = $description = $price = $item_condition = $location = '';
        } else {
            $errors[] = 'Could not save your listing. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

$csrfToken = create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell an Item - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container selling-container">
        <h2>List an Item for Sale</h2>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <!-- enctype is REQUIRED when uploading files. -->
        <form action="selling.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="item-title">Item Title *</label>
                <input type="text" id="item-title" name="item_title"
                       placeholder="e.g. iPhone 13 Pro Max"
                       value="<?= htmlspecialchars($title) ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select a Category</option>
                    <?php
                    $categories = [
                        'electronics' => 'Electronics',
                        'clothing'    => 'Clothing & Accessories',
                        'furniture'   => 'Furniture & Home',
                        'vehicles'    => 'Vehicles',
                        'appliances'  => 'Appliances',
                        'sports'      => 'Sports & Outdoors',
                        'books'       => 'Books & Media',
                        'toys'        => 'Toys & Games',
                        'other'       => 'Other',
                    ];
                    foreach ($categories as $value => $label):
                        $selected = ($category === $value) ? 'selected' : '';
                    ?>
                        <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description"
                          placeholder="Describe your item — condition, features, reason for selling, etc."
                          required><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="price-row">
                <div class="form-group">
                    <label for="price">Price (R) *</label>
                    <input type="number" id="price" name="price"
                           placeholder="0.00" min="0" step="0.01"
                           value="<?= htmlspecialchars($price) ?>" required>
                </div>

                <div class="form-group">
                    <label for="item-condition">Condition *</label>
                    <select id="item-condition" name="item_condition" required>
                        <option value="">Select Condition</option>
                        <?php
                        $conditions = [
                            'new'      => 'New',
                            'like_new' => 'Like New',
                            'good'     => 'Good',
                            'fair'     => 'Fair',
                            'poor'     => 'Poor',
                        ];
                        foreach ($conditions as $value => $label):
                            $selected = ($item_condition === $value) ? 'selected' : '';
                        ?>
                            <option value="<?= $value ?>" <?= $selected ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" id="location" name="location"
                       placeholder="e.g. Cape Town, Western Cape"
                       value="<?= htmlspecialchars($location) ?>" required>
            </div>

            <div class="form-group">
                <label>Item Images * <span class="hint">(1 required, up to 4 total — JPG, PNG, WEBP, max 5MB each)</span></label>

                <!-- 4 separate file inputs. Only the first is required. -->
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" required>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp">
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp">
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp">
            </div>

            <button type="submit">Submit Listing</button>
        </form>

        <div class="switch-link">
            Changed your mind? <a href="option.php">Go Back</a>
        </div>
    </div>

</body>
</html>