<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Private websocket channels (see App\Services\Realtime\Realtime). Events on
| them only say that something changed; the data itself is fetched through
| the normal, access-checked endpoints.
*/

Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);
