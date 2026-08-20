<?php
$appearance = ['background_type' => 'gradient', 'cover_value' => 'url("image.png")'];
$json = json_encode($appearance, JSON_HEX_QUOT | JSON_HEX_APOS);
echo "x-data=\"dashboardAppearance($json)\"\n";
