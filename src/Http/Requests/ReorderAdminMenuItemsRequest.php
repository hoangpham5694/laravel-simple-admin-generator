<?php

namespace HoangPhamDev\SimpleAdminGenerator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderAdminMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                'exists:admin_menu_items,id',
            ],
            'items.*.parent_id' => [
                'nullable',
                'integer',
                'exists:admin_menu_items,id',
            ],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
