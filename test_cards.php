<?php
$cards = \App\Models\Card::where('title', 'like', '%1701 PRO%')->get(['id', 'title', 'sync_group_id', 'status', 'board_id'])->toArray();
echo json_encode($cards, JSON_PRETTY_PRINT);
