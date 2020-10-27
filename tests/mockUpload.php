<?php
/*
 * Copyright (c) Spotloc 2020. Tous droits réservés.
 */

namespace App\Model\Behavior;

function move_uploaded_file($tmpName, $to): bool
{
    return true;
}


function exif_read_data() {
    return null;
}