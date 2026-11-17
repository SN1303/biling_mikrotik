@php
$configData = Helper::appClasses();
$currentUser = auth()->user();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

  <!-- ! Hide app brand if navbar-full -->
  @if(!isset($navbarFull))
  <div class="app-brand demo">
    <a href="{{url('/')}}" class="app-brand-link">
      <span class="app-brand-logo demo">
        <i class="mdi mdi-access-point-network fs-3 text-primary"></i>
      </span>
      <span class="app-brand-text demo menu-text fw-bold ms-2">MRYES NET</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
      <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11.4854 4.88844C11.0081 4.41121 10.2344 4.41121 9.75715 4.88844L4.51028 10.1353C4.03297 10.6126 4.03297 11.3865 4.51028 11.8638L9.75715 17.1107C10.2344 17.5879 11.0081 17.5879 11.4854 17.1107C11.9626 16.6334 11.9626 15.8597 11.4854 15.3824L7.96672 11.8638C7.48942 11.3865 7.48942 10.6126 7.96672 10.1353L11.4854 6.61667C11.9626 6.13943 11.9626 5.36568 11.4854 4.88844Z" fill="currentColor" fill-opacity="0.6" />
        <path d="M15.8683 4.88844L10.6214 10.1353C10.1441 10.6126 10.1441 11.3865 10.6214 11.8638L15.8683 17.1107C16.3455 17.5879 17.1192 17.5879 17.5965 17.1107C18.0737 16.6334 18.0737 15.8597 17.5965 15.3824L14.0778 11.8638C13.6005 11.3865 13.6005 10.6126 14.0778 10.1353L17.5965 6.61667C18.0737 6.13943 18.0737 5.36568 17.5965 4.88844C17.1192 4.41121 16.3455 4.41121 15.8683 4.88844Z" fill="currentColor" fill-opacity="0.38" />
      </svg>
    </a>
  </div>
  @endif

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">

    {{-- ================= UTAMA ================= --}}
    <li class="menu-header fw-medium mt-3">
      <span class="menu-header-text">Utama</span>
    </li>
    <li class="menu-item {{ request()->is('/') ? 'active' : '' }}">
      <a href="{{ url('/') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-view-dashboard-outline"></i>
        <div>Dashboard</div>
      </a>
    </li>

    {{-- ================= JARINGAN & SERVER ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 4, 7))
    <li class="menu-header fw-medium mt-3">
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
        <div>Peta Jaringan</div>
      </a>
    </li>
    @endif

    {{-- ================= PELANGGAN ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 4, 6, 7))
    <li class="menu-header fw-medium mt-3">
      <span class="menu-header-text">Pelanggan</span>
    </li>
    <li class="menu-item {{ request()->is('user') || (request()->is('user/*') && !request()->is('user/create') && !request()->is('user/list/mapping') && !request()->is('user/show/map')) ? 'active' : '' }}">
      <a href="{{ route('user.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-account-group-outline"></i>
        <div>Daftar Pelanggan</div>
      </a>
    </li>

    @if($currentUser->hasRole(1, 2, 4, 7))
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
    <li class="menu-header fw-medium mt-3">
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
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 6, 7))
    <li class="menu-header fw-medium mt-3">
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
    <li class="menu-header fw-medium mt-3">
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
    <li class="menu-header fw-medium mt-3">
      <span class="menu-header-text">Sistem</span>
    </li>
    <li class="menu-item {{ request()->is('settings*') ? 'active' : '' }}">
      <a href="{{ route('settings.sync.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-cog-sync-outline"></i>
        <div>Sinkronisasi Sistem</div>
      </a>
    </li>
    @endif

    {{-- ================= AKUN & KELUAR ================= --}}
    <li class="menu-header fw-medium mt-3">
      <span class="menu-header-text">Akun</span>
    </li>
    <li class="menu-item">
      <a href="{{ route('logout') }}" class="menu-link text-danger" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
        <i class="menu-icon tf-icons mdi mdi-logout text-danger"></i>
        <div>Keluar / Logout</div>
      </a>
      <form method="POST" id="sidebar-logout-form" action="{{ route('logout') }}" class="d-none">
        @csrf
      </form>
    </li>

  </ul>

  @if($currentUser)
  <div class="p-3 border-top mt-auto bg-menu-theme">
    <div class="d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-2 overflow-hidden">
        <div class="avatar avatar-sm flex-shrink-0">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 32px; height: 32px; background: linear-gradient(135deg, #2563eb, #0ea5e9); font-size: 0.8rem;">
            {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
          </div>
        </div>
        <div class="text-truncate" style="line-height: 1.2;">
          <div class="fw-semibold text-dark text-truncate" style="font-size: 0.8125rem;">{{ $currentUser->name ?? '-' }}</div>
          <small class="text-muted" style="font-size: 0.7rem;">{{ $currentUser->role_name ?? 'User' }}</small>
        </div>
      </div>
      <a href="{{ route('logout') }}" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Logout" onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
        <i class="mdi mdi-power fs-5"></i>
      </a>
    </div>
  </div>
  @endif

</aside>
