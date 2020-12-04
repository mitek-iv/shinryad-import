<?php
    $start = microtime(true);
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
    
	include("classes/config.class.php");
    include("classes/commonClass.class.php");
	include("classes/db.class.php");

    $conf = new config("includes/config.inc.php");
    $db = new db();
    $res = $db->query("SELECT id, type_id, marka, model, size, `count`, `price` FROM imp_product_compact WHERE 1 ORDER BY type_id, marka, model, size");

    $products = [];
    $is_processed = [];
    if(!empty($res))
        foreach($res as $item) {
            $products[$item["type_id"]][$item["marka"]][$item["model"]][$item["size"]] = sprintf("%d|%d|%d", $item["id"], $item["count"], $item["price"]);
            $is_processed[] = $item["id"];
            }

    if (!empty($is_processed)) {
        $db->query(sprintf("UPDATE imp_product_compact SET is_processed = 1 WHERE id IN (%s)", implode(", ", $is_processed)));
    }
        
    unset($db);

    printArray($products);
?>