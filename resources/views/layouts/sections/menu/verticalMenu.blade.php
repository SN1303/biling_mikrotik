@php
$configData = Helper::appClasses();
$currentUser = auth()->user();
$roleBadgeClass = 'badge-role-2';
if ($currentUser) {
    if ($currentUser->idRole == 1) $roleBadgeClass = 'badge-role-1';
    elseif ($currentUser->idRole == 2) $roleBadgeClass = 'badge-role-2';
    elseif ($currentUser->idRole == 3) $roleBadgeClass = 'badge-role-3';
    elseif ($currentUser->idRole == 4) $roleBadgeClass = 'badge-role-4';
    elseif ($currentUser->idRole == 6) $roleBadgeClass = 'badge-role-6';
}
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

  <!-- Brand header -->
  <div class="app-brand demo d-flex align-items-center justify-content-between">
    <a href="{{ url('/') }}" class="app-brand-link gap-2 d-flex align-items-center text-decoration-none">
      <div class="app-brand-logo demo d-flex align-items-center justify-content-center p-1 rounded-3" style="background: linear-gradient(135deg, #2563eb, #0ea5e9);">
        <i class="mdi mdi-access-point-network text-white" style="font-size: 20px;"></i>
      </div>
      <div class="d-flex flex-column">
        <span class="app-brand-text demo menu-text fw-bold text-white" style="letter-spacing: -0.02em;">MRYES NET</span>
        <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;">BILLING & NOC</span>
      </div>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block">
      <i class="mdi mdi-chevron-left align-middle text-muted" style="font-size: 22px;"></i>
    </a>
  </div>

  @if($currentUser)
  <!-- Sidebar User Profile Card -->
  <div class="sidebar-user-card">
    <div class="sidebar-user-avatar">
      {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
    </div>
    <div class="sidebar-user-meta flex-grow-1">
      <div class="sidebar-user-name" title="{{ $currentUser->name }}">{{ $currentUser->name }}</div>
      <span class="badge {{ $roleBadgeClass }}" style="font-size: 0.68rem; padding: 0.2rem 0.5rem;">
        {{ $currentUser->role_name ?? 'User' }}
      </span>
    </div>
  </div>
  @endif

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">

    {{-- ================= UTAMA ================= --}}
    <li class="menu-header">
      <span class="menu-header-text">Utama</span>
    </li>
    <li class="menu-item {{ request()->is('/') ? 'active' : '' }}">
      <a href="{{ url('/') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-view-dashboard-outline"></i>
        <div>Dashboard</div>
      </a>
    </li>

    {{-- ================= JARINGAN & SERVER ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 4))
    <li class="menu-header">
      <span class="menu-header-text">Jaringan & Server</span>
    </li>

    {{-- Mikrotik Group --}}
    @if($currentUser->hasRole(1, 2))
    <li class="menu-item {{ request()->is('mikrotik*') ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons mdi mdi-router-wireless"></i>
        <div>MikroTik</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item {{ request()->routeIs('mikrotik.index') ? 'active' : '' }}">
          <a href="{{ route('mikrotik.index') }}" class="menu-link">
            <div>Server MikroTik</div>
          </a>
        </li>
        <li class="menu-item {{ request()->routeIs('mikrotik.connection-check') ? 'active' : '' }}">
          <a href="{{ route('mikrotik.connection-check') }}" class="menu-link">
            <div>Cek Koneksi</div>
          </a>
        </li>
        <li class="menu-item {{ request()->routeIs('mikrotik.pppoe-manage') ? 'active' : '' }}">
          <a href="{{ route('mikrotik.pppoe-manage') }}" class="menu-link">
            <div>Kelola PPPoE</div>
          </a>
        </li>
        <li class="menu-item {{ request()->routeIs('mikrotik.kelola-isolir') ? 'active' : '' }}">
          <a href="{{ route('mikrotik.kelola-isolir') }}" class="menu-link">
            <div>Kelola Isolir</div>
          </a>
        </li>
        <li class="menu-item {{ request()->routeIs('mikrotik.sync-customers') ? 'active' : '' }}">
          <a href="{{ route('mikrotik.sync-customers') }}" class="menu-link">
            <div>Sinkron Pelanggan</div>
          </a>
        </li>
      </ul>
    </li>

    {{-- OLT --}}
    <li class="menu-item {{ request()->is('olt*') ? 'active' : '' }}">
      <a href="{{ route('olt.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-server-network"></i>
        <div>OLT</div>
      </a>
    </li>
    @endif

    {{-- ODC, ODP & Maps (Role 1, 2, 4) --}}
    <li class="menu-item {{ request()->is('odc*') ? 'active' : '' }}">
      <a href="{{ route('odc.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-hub-outline"></i>
        <div>ODC</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('odp*') ? 'active' : '' }}">
      <a href="{{ route('odp.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-transit-connection-variant"></i>
        <div>ODP</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('google-map*') ? 'active' : '' }}">
      <a href="{{ route('google-map.standalone') }}" class="menu-link" target="_blank">
        <i class="menu-icon tf-icons mdi mdi-map-marker-radius-outline"></i>
        <div class="d-flex align-items-center justify-content-between w-100">
          <span>Peta Jaringan</span>
          <i class="mdi mdi-open-in-new ms-auto" style="font-size: 14px; opacity: 0.7;"></i>
        </div>
      </a>
    </li>
    @endif

    {{-- ================= PELANGGAN ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 4, 6))
    <li class="menu-header">
      <span class="menu-header-text">Pelanggan</span>
    </li>
    <li class="menu-item {{ request()->is('user') || (request()->is('user/*') && !request()->is('user/create') && !request()->is('user/list/mapping') && !request()->is('user/show/map')) ? 'active' : '' }}">
      <a href="{{ route('user.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-account-group-outline"></i>
        <div>Daftar Pelanggan</div>
      </a>
    </li>

    @if($currentUser->hasRole(1, 2, 4))
    <li class="menu-item {{ request()->is('user/create') ? 'active' : '' }}">
      <a href="{{ route('user.create') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-account-plus-outline"></i>
        <div>Tambah Pelanggan</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('user/list/mapping') ? 'active' : '' }}">
      <a href="{{ route('user.mapping') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-map-search-outline"></i>
        <div>Mapping Pelanggan</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('user/show/map') ? 'active' : '' }}">
      <a href="{{ route('user.show.mapping') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-map-marker-distance"></i>
        <div>Lihat Map ODP</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('area*') ? 'active' : '' }}">
      <a href="{{ route('area.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-map-marker-multiple-outline"></i>
        <div>Area Wilayah</div>
      </a>
    </li>
    @endif
    @endif

    {{-- ================= LAYANAN & INTERNET ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2))
    <li class="menu-header">
      <span class="menu-header-text">Layanan & Internet</span>
    </li>
    <li class="menu-item {{ request()->is('kategori*') ? 'active' : '' }}">
      <a href="{{ route('kategori.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-shape-outline"></i>
        <div>Kategori Paket</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('paket*') ? 'active' : '' }}">
      <a href="{{ route('paket.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-speedometer"></i>
        <div>Paket Internet</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('ip-pool*') ? 'active' : '' }}">
      <a href="{{ route('ip-pool.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-ip-network-outline"></i>
        <div>IP Pool</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('address-list*') ? 'active' : '' }}">
      <a href="{{ route('address-list.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-format-list-checks"></i>
        <div>Address List</div>
      </a>
    </li>
    @endif

    {{-- ================= TAGIHAN & KASIR ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 6))
    <li class="menu-header">
      <span class="menu-header-text">Tagihan & Kasir</span>
    </li>
    <li class="menu-item {{ request()->is('tagihan') || (request()->is('tagihan/*') && !request()->is('tagihan/lunas') && !request()->is('tagihan/rekap') && !request()->is('tagihan/generate')) ? 'active' : '' }}">
      <a href="{{ route('tagihan.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-receipt-text-outline"></i>
        <div>Tagihan Aktif</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('tagihan/lunas') ? 'active' : '' }}">
      <a href="{{ route('tagihan.lunas') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-check-decagram-outline"></i>
        <div>Tagihan Lunas</div>
      </a>
    </li>

    @if($currentUser->hasRole(1, 2, 3))
    <li class="menu-item {{ request()->is('tagihan/rekap') ? 'active' : '' }}">
      <a href="{{ route('tagihan.rekap') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-file-chart-outline"></i>
        <div>Rekap Bulanan</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('tagihan/generate') ? 'active' : '' }}">
      <a href="{{ route('tagihan.generate') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-lightning-bolt-circle"></i>
        <div>Generate Tagihan</div>
      </a>
    </li>
    @endif
    @endif

    {{-- ================= MANAJEMEN STAF (SUPER ADMIN ONLY) ================= --}}
    @if($currentUser && $currentUser->isSuperAdmin())
    <li class="menu-header">
      <span class="menu-header-text">Keamanan & Tim</span>
    </li>
    <li class="menu-item {{ request()->is('staff*') ? 'active' : '' }}">
      <a href="{{ route('staff.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-shield-account-outline"></i>
        <div>Manajemen Staf</div>
      </a>
    </li>
    @endif

    {{-- ================= SISTEM ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2))
    <li class="menu-header">
      <span class="menu-header-text">Sistem</span>
    </li>
    <li class="menu-item {{ request()->is('settings*') ? 'active' : '' }}">
      <a href="{{ route('settings.sync.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-cog-sync-outline"></i>
        <div>Sinkronisasi Sistem</div>
      </a>
    </li>
    @endif

  </ul>

</aside>
