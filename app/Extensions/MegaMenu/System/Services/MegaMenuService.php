<?php

namespace App\Extensions\MegaMenu\System\Services;

use App\Extensions\MegaMenu\System\Models\MegaMenuItem;

class MegaMenuService
{
    public function parentMenuOrderUpdate(int $megaMenuId, array $data): void
    {
        foreach ($data as $key => $value) {
            MegaMenuItem::query()
                ->where('mega_menu_id', $megaMenuId)
                ->where('id', $value)
                ->update([
                    'parent_id' => null,
                    'order'     => $key,
                ]);
        }
    }

    public function subMenuOrderUpdate(int $megaMenuId, array $data): void
    {

        $order = 0;

        $lastParent = 0;

        foreach ($data as $key => $value) {
            if ($value != $lastParent) {
                $order = 0;
            } else {
                $order = $order + 1;
            }
            $item = MegaMenuItem::query()
                ->where('mega_menu_id', $megaMenuId)
                ->where('id', $key)
                ->first();

            $item?->update([
                'order'     => $order,
                'parent_id' => $value,
            ]);

            $lastParent = $value;
        }
    }
}
