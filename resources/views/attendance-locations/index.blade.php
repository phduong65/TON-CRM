@extends('layouts.admin')

@section('title', 'Điểm chấm công')
@section('page-title', 'Điểm chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-subtitle')
    Toạ độ GPS + danh sách IP WiFi văn phòng dùng để xác thực chấm công
@endsection

@section('page-actions')
    @can('create-attendance-locations')
    <button onclick="openCreateLocationModal()" class="btn-primary">
        <i class="bi bi-plus-lg"></i>
        <span>Thêm điểm chấm công</span>
    </button>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('attendance-locations.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
                    <select name="branch_id" class="form-input h-9 text-sm w-full min-w-[180px]">
                        <option value="">Tất cả</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-secondary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if(request('branch_id'))
                <a href="{{ route('attendance-locations.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Tên điểm</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th">Toạ độ</th>
                            <th class="table-th text-center">Bán kính</th>
                            <th class="table-th">IP văn phòng</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $l)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $l->name }}</td>
                            <td class="table-td text-slate-500 text-sm">{{ $l->branch?->name ?? '—' }}</td>
                            <td class="table-td text-xs font-mono">{{ $l->latitude }}, {{ $l->longitude }}</td>
                            <td class="table-td text-center text-sm">{{ $l->radius_meters }}m</td>
                            <td class="table-td text-xs font-mono text-slate-500">
                                {{ implode(', ', $l->allowed_ips ?? []) ?: '—' }}
                                @if($warning = ($ipMismatchWarnings[$l->id] ?? null))
                                    <p class="mt-1 flex items-center gap-1 text-amber-600 dark:text-amber-400 font-sans"
                                       title="Có thể IP văn phòng đã đổi — nhân viên có GPS đúng nhưng IP không khớp danh sách trên">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        {{ $warning['count'] }} NV chấm công lỗi IP hôm nay
                                        @if($warning['last_ip'])
                                            (IP gần nhất: {{ $warning['last_ip'] }})
                                        @endif
                                    </p>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                @if($l->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-attendance-locations')
                                    <button onclick='openEditLocationModal({{ json_encode([
                                        "id"=>$l->id,"branch_id"=>$l->branch_id,"name"=>$l->name,
                                        "latitude"=>$l->latitude,"longitude"=>$l->longitude,"radius_meters"=>$l->radius_meters,
                                        "allowed_ips"=>implode("\n", $l->allowed_ips ?? []),"is_active"=>$l->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-attendance-locations')
                                    <button onclick="openDeleteLocationModal({{ $l->id }}, '{{ addslashes($l->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-geo-alt text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có điểm chấm công nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($locations->hasPages())
        <div class="card-footer">
            {{ $locations->links() }}
        </div>
        @endif
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('modals')
    @include('attendance-locations.partials.create-modal')
    @include('attendance-locations.partials.edit-modal')
    @include('attendance-locations.partials.delete-modal')
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const LOCATION_DEFAULT_CENTER = { lat: 16.0544, lng: 108.2022 }; // Đà Nẵng — fallback khi chưa có toạ độ
const locationMaps = {}; // { create: {map, marker, circle}, edit: {...} }

function openCreateLocationModal() {
    openModal('createLocationModal');
    ensureLocationMap('create', 'createLocationMap', 'createLocationLat', 'createLocationLng', 'createLocationRadius', 'createLocationAddress');
}

function openEditLocationModal(data) {
    document.getElementById('editLocationEditId').value = data.id ?? '';
    document.getElementById('editLocationBranch').value = data.branch_id ?? '';
    document.getElementById('editLocationName').value = data.name ?? '';
    document.getElementById('editLocationLat').value = data.latitude ?? '';
    document.getElementById('editLocationLng').value = data.longitude ?? '';
    document.getElementById('editLocationRadius').value = data.radius_meters ?? 100;
    document.getElementById('editLocationIps').value = data.allowed_ips ?? '';
    document.getElementById('editLocationActive').checked = !!data.is_active;
    document.getElementById('editLocationForm').action = '/attendance-locations/' + data.id;
    document.getElementById('editLocationAddress').textContent = '';
    openModal('editLocationModal');

    const lat = parseFloat(data.latitude) || LOCATION_DEFAULT_CENTER.lat;
    const lng = parseFloat(data.longitude) || LOCATION_DEFAULT_CENTER.lng;
    const radius = parseInt(data.radius_meters) || 100;
    ensureLocationMap('edit', 'editLocationMap', 'editLocationLat', 'editLocationLng', 'editLocationRadius', 'editLocationAddress');
    moveLocationMapTo('edit', lat, lng, radius);
}

function openDeleteLocationModal(id, name) {
    document.getElementById('deleteLocationName').textContent = name;
    document.getElementById('deleteLocationForm').action = '/attendance-locations/' + id;
    openModal('deleteLocationModal');
}

// ── Bản đồ chọn vị trí GPS (Leaflet + OpenStreetMap) ────────────────────
function ensureLocationMap(key, mapElId, latInputId, lngInputId, radiusInputId, addressElId) {
    if (locationMaps[key]) {
        setTimeout(() => locationMaps[key].map.invalidateSize(), 150);
        return locationMaps[key];
    }

    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    const radiusInput = document.getElementById(radiusInputId);
    const lat = parseFloat(latInput.value) || LOCATION_DEFAULT_CENTER.lat;
    const lng = parseFloat(lngInput.value) || LOCATION_DEFAULT_CENTER.lng;

    const map = L.map(mapElId).setView([lat, lng], 16);
    const isDark = document.documentElement.classList.contains('dark');
    const tileUrl = isDark
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
    L.tileLayer(tileUrl, {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    }).addTo(map);

    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    const circle = L.circle([lat, lng], {
        radius: parseInt(radiusInput.value) || 100,
        color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.1, weight: 1,
    }).addTo(map);

    function applyLatLng(newLat, newLng, zoom) {
        latInput.value = newLat.toFixed(7);
        lngInput.value = newLng.toFixed(7);
        marker.setLatLng([newLat, newLng]);
        circle.setLatLng([newLat, newLng]);
        if (zoom) { map.setView([newLat, newLng], zoom); } else { map.panTo([newLat, newLng]); }
        reverseGeocodeLocation(newLat, newLng, addressElId);
    }

    marker.on('dragend', () => {
        const p = marker.getLatLng();
        applyLatLng(p.lat, p.lng);
    });
    map.on('click', (e) => applyLatLng(e.latlng.lat, e.latlng.lng));
    radiusInput.addEventListener('input', () => circle.setRadius(parseInt(radiusInput.value) || 0));

    locationMaps[key] = { map, marker, circle, applyLatLng };
    setTimeout(() => map.invalidateSize(), 150);
    return locationMaps[key];
}

function moveLocationMapTo(key, lat, lng, radius) {
    const m = locationMaps[key];
    if (!m) return;
    setTimeout(() => {
        m.map.invalidateSize();
        m.map.setView([lat, lng], 16);
        m.marker.setLatLng([lat, lng]);
        m.circle.setLatLng([lat, lng]);
        m.circle.setRadius(radius);
    }, 150);
}

let _geocodeTimer = null;
function reverseGeocodeLocation(lat, lng, addressElId) {
    const el = document.getElementById(addressElId);
    if (!el) return;
    el.textContent = 'Đang tra cứu địa chỉ...';
    clearTimeout(_geocodeTimer);
    _geocodeTimer = setTimeout(() => {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => { el.textContent = data.display_name ? '📍 ' + data.display_name : ''; })
            .catch(() => { el.textContent = ''; });
    }, 500);
}

// ── Nút "Lấy vị trí hiện tại" (GPS) ──────────────────────────────────────
function detectLocationGps(key) {
    if (!navigator.geolocation) {
        window.pcrmAlert?.('error', 'Không hỗ trợ', 'Trình duyệt không hỗ trợ định vị GPS.');
        return;
    }
    const ids = key === 'create'
        ? ['createLocationMap', 'createLocationLat', 'createLocationLng', 'createLocationRadius', 'createLocationAddress']
        : ['editLocationMap', 'editLocationLat', 'editLocationLng', 'editLocationRadius', 'editLocationAddress'];

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const m = ensureLocationMap(key, ...ids);
            m.applyLatLng(pos.coords.latitude, pos.coords.longitude, 17);
        },
        () => {
            window.pcrmAlert?.('error', 'Không lấy được vị trí', 'Vui lòng cấp quyền định vị (GPS) cho trình duyệt và thử lại.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

// ── Nút "Lấy IP hiện tại" (WiFi/mạng văn phòng) ─────────────────────────
function detectLocationIp(textareaId) {
    fetch('{{ route('attendance-locations.detect-ip') }}')
        .then(res => res.json())
        .then(data => {
            if (!data.ip) return;
            const ta = document.getElementById(textareaId);
            const lines = ta.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
            if (!lines.includes(data.ip)) lines.push(data.ip);
            ta.value = lines.join('\n');
        })
        .catch(() => {
            window.pcrmAlert?.('error', 'Lỗi', 'Không thể lấy địa chỉ IP hiện tại.');
        });
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editLocationModal')
    openEditLocationModal({
        id: '{{ old("_edit_id") }}',
        branch_id: '{{ old("branch_id") }}',
        name: '{{ old("name") }}',
        latitude: '{{ old("latitude") }}',
        longitude: '{{ old("longitude") }}',
        radius_meters: '{{ old("radius_meters") }}',
        allowed_ips: {{ Illuminate\Support\Js::from(old('allowed_ips')) }},
        is_active: {{ old("is_active") ? "true" : "false" }}
    });
    @elseif(old('_modal') === 'createLocationModal')
    openCreateLocationModal();
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
