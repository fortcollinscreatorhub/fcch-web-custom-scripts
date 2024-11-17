<?php
// Copyright 2023 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT
?>
<script>
function doSearchRow(tr, value) {
    hidden = true;
    for (let i = 0; i < tr.cells.length; i++) {
        cell = tr.cells[i];
        cellText = cell.innerHTML.toLowerCase();
        if (cellText.includes(value)) {
            hidden = false;
            break;
        }
    }
    if (hidden) {
        tr.setAttribute('hidden', 'hidden');
    } else {
        tr.removeAttribute('hidden');
    }
}
function doSearch(tableId, value) {
    value = value.toLowerCase();
    if (value == "") {
        fn = tr => tr.removeAttribute('hidden');
    } else {
        fn = tr => doSearchRow(tr, value);
    }
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(fn);
}
</script>
