<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('AdminAreaHeadOutput', 1, function($vars) {
    return <<<HTML
    <script type="text/javascript">
        $(document).ready(function(){
            var \$menuItem = $("<li><a href='addonmodules.php?module=relaystatistics'>Relay Statistics</a></li>");
            $('#menu').find('ul.nav > li').first().find('ul').first().append(\$menuItem);
        });
    </script>
    HTML;