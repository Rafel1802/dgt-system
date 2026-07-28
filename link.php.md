<?php
$targetFolder = $_SERVER['DOCUMENT_ROOT'].'/dgt-system/storage/app/public';
$linkFolder = $_SERVER['DOCUMENT_ROOT'].'/dgt-system/public/storage';
symlink($targetFolder, $linkFolder);
echo 'Symlink created successfully!';
?>

#Here is how to fix the invalid storage link:
Delete the broken link: First, right-click that red storage file with the "invalid link" warning and delete it.
Create a temporary helper file: In that exact same folder (public_html/dgt-system/public), click the "+ New file" button.
Name the file exactly: link.php
Open link.php, paste this exact code into it, and save it:
php
<?php
$targetFolder = $_SERVER['DOCUMENT_ROOT'].'/dgt-system/storage/app/public';
$linkFolder = $_SERVER['DOCUMENT_ROOT'].'/dgt-system/public/storage';
symlink($targetFolder, $linkFolder);
echo 'Symlink created successfully!';
?>
Run the file: Open a new tab in your web browser and go to your website address followed by /link.php (for example: https://yourdomain.com/link.php).
You should see a white page saying "Symlink created successfully!".
Clean up: Go back to your Hostinger file manager and refresh the page. You will see a healthy, working storage folder! You can now safely delete the link.php file you creat/#

