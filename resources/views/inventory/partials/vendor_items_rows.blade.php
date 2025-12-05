@foreach($item_data as $v)
<tr>

    <td>
        <input type="checkbox" name="chkbx_item_id_arr[]" value="{{ $v->item_id }}" checked>
    </td>

    <td>{{ $v->item_name }}</td>

    {{-- PRICE --}}
    <td>
        {{ number_format($v->price, 2) }}
        <input type="hidden"
               id="price_{{ $v->item_id }}"
               name="price[{{ $v->item_id }}]"
               value="{{ $v->price }}">
    </td>

    {{-- QTY --}}
    <td>
        <input type="text"
               class="cls_qty"
               data-id="{{ $v->item_id }}"
               id="qty_{{ $v->item_id }}"
               name="qty[{{ $v->item_id }}]"
               value="1"
               style="width:70px;">
    </td>

    {{-- AMOUNT --}}
    <td>
        <input type="text"
               id="amount_{{ $v->item_id }}"
               name="amount[{{ $v->item_id }}]"
               readonly style="width:90px;">
    </td>

    {{-- DISCOUNT % --}}
    <td>
        <input type="text"
               class="cls_dis"
               data-id="{{ $v->item_id }}"
               id="dis_per_{{ $v->item_id }}"
               name="dis_per[{{ $v->item_id }}]"
               value="0"
               style="width:60px;">
    </td>

    {{-- DISCOUNT AMOUNT --}}
    <td>
        <input type="text"
               id="dis_amount_value_{{ $v->item_id }}"
               name="dis_amount_value[{{ $v->item_id }}]"
               readonly style="width:80px;">
    </td>

    {{-- AMOUNT AFTER DISCOUNT --}}
    <td>
        <input type="text"
               id="after_dis_amount_{{ $v->item_id }}"
               name="after_dis_amount[{{ $v->item_id }}]"
               readonly style="width:80px;">
    </td>

    {{-- TAX % --}}
    <td>
        <input type="text"
               class="cls_tax"
               data-id="{{ $v->item_id }}"
               id="tax_per_{{ $v->item_id }}"
               name="tax_per[{{ $v->item_id }}]"
               value="0"
               style="width:60px;">
    </td>

    {{-- TAX AMOUNT --}}
    <td>
        <input type="text"
               id="tax_amount_value_{{ $v->item_id }}"
               name="tax_amount_value[{{ $v->item_id }}]"
               readonly style="width:80px;">
    </td>

    {{-- FINAL TOTAL --}}
    <td>
        <input type="text"
               id="after_tax_amount_{{ $v->item_id }}"
               name="after_tax_amount[{{ $v->item_id }}]"
               readonly style="width:90px;">
    </td>

</tr>
@endforeach
