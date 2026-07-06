<div id="editLocationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editLocationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa điểm chấm công
            </h3>
            <button onclick="closeModal('editLocationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editLocationForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editLocationModal">
            <input type="hidden" id="editLocationEditId" name="_edit_id">
            <div>
                <label class="form-label">Chi nhánh <span class="text-red-500">*</span></label>
                <select id="editLocationBranch" name="branch_id" class="form-input" required>
                    <option value="">-- Chọn chi nhánh --</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Tên điểm chấm công <span class="text-red-500">*</span></label>
                <input type="text" id="editLocationName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="form-label !mb-0">Vị trí GPS <span class="text-red-500">*</span></label>
                    <button type="button" onclick="detectLocationGps('edit')"
                            class="text-xs inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-crosshair"></i> Lấy vị trí hiện tại
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <input type="text" id="editLocationLat" name="latitude" class="form-input" required>
                        @error('latitude') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" id="editLocationLng" name="longitude" class="form-input" required>
                        @error('longitude') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p id="editLocationAddress" class="text-xs text-slate-400 mt-1 min-h-[1rem]"></p>
                <div id="editLocationMap" class="mt-2 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700" style="height:220px;"></div>
                <p class="text-xs text-slate-400 mt-1">Kéo ghim hoặc bấm vào bản đồ để chọn vị trí chính xác.</p>
            </div>
            <div>
                <label class="form-label">Bán kính cho phép (mét) <span class="text-red-500">*</span></label>
                <input type="number" id="editLocationRadius" name="radius_meters" class="form-input" min="10" required>
                @error('radius_meters') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="form-label !mb-0">IP văn phòng (mỗi dòng 1 IP hoặc CIDR)</label>
                    <button type="button" onclick="detectLocationIp('editLocationIps')"
                            class="text-xs inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-wifi"></i> Lấy IP hiện tại
                    </button>
                </div>
                <textarea id="editLocationIps" name="allowed_ips" rows="3" class="form-input font-mono text-sm"></textarea>
                @error('allowed_ips') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="editLocationActive" name="is_active" value="1"
                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Đang hoạt động</span>
            </label>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editLocationModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
