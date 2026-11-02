<?php

return [
    // Orders with a total at or above this amount need a second (admin) approval.
    'approval_threshold' => (float) env('PO_APPROVAL_THRESHOLD', 1000),
];