{{--
    Bộ chọn quyền theo nhóm — dùng trong modal Vai trò và mục "Quyền riêng" của modal Người dùng.
    Hành vi (tìm kiếm, đếm, chọn nhóm, khoá quyền đã có qua vai trò): resources/js/permission-picker.js

    Props:
      - groups:  ['Tên nhóm' => ['perm-key' => 'Nhãn', ...], ...]  (Controller::permissionGroups())
      - checked: danh sách quyền được tick sẵn (VD old('permissions', []))
      - id:      id của khối (để script trang gọi PermPicker.set/lockViaRole)
--}}
@props(['groups' => [], 'checked' => [], 'id' => null])

<div {{ $attributes->merge(['class' => 'perm-picker']) }} data-perm-picker @if ($id) id="{{ $id }}" @endif>
    <div class="perm-picker-toolbar">
        <div class="relative min-w-0 flex-1">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
            <input type="search" data-perm-search class="form-input h-9 pl-8 text-sm" placeholder="Tìm quyền theo tên hoặc mã…"
                   aria-label="Tìm quyền" autocomplete="off">
        </div>
        <div class="flex items-center gap-1 shrink-0">
            <button type="button" data-perm-all class="btn-ghost btn-sm">Chọn tất cả</button>
            <button type="button" data-perm-none class="btn-ghost btn-sm">Bỏ chọn</button>
        </div>
    </div>

    <div class="perm-picker-groups">
        @foreach ($groups as $groupName => $perms)
            <fieldset class="perm-group" data-perm-group>
                <legend class="sr-only">{{ $groupName }}</legend>
                <div class="perm-group-head" aria-hidden="true">
                    <span class="perm-group-name">{{ $groupName }}</span>
                    <span class="perm-group-count" data-group-count>0/{{ count($perms) }}</span>
                    <button type="button" data-group-toggle class="perm-group-toggle" aria-pressed="false">Chọn nhóm</button>
                </div>
                <div class="perm-grid">
                    @foreach ($perms as $permKey => $permLabel)
                        <label class="perm-item" data-perm-item data-search="{{ mb_strtolower($permLabel . ' ' . $permKey) }}">
                            <input type="checkbox" name="permissions[]" value="{{ $permKey }}" class="perm-cb"
                                   @checked(in_array($permKey, $checked, true))>
                            <span class="min-w-0">
                                <span class="perm-label">{{ $permLabel }}</span>
                                <span class="perm-key">{{ $permKey }}</span>
                                <span class="perm-via"><i class="bi bi-shield-check" aria-hidden="true"></i> Có qua vai trò</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p data-perm-empty hidden class="py-6 text-center text-sm text-slate-400">Không tìm thấy quyền phù hợp</p>
    </div>
</div>
