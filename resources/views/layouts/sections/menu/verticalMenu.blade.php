@php
$configData = Helper::appClasses();
$currentUser = auth()->user();
$isDark = ($configData['style'] ?? 'light') === 'dark';

// Jumlah tagihan belum lunas untuk badge menu (cache singkat agar ringan)
$sidebarUnpaidCount = 0;
if ($currentUser && $currentUser->hasRole(1, 2, 3, 6, 7)) {
  try {
    $sidebarUnpaidCount = \Illuminate\Support\Facades\Cache::remember('sidebar_unpaid_count', 60, function () {
      return \Illuminate\Support\Facades\DB::table('lamtim_tagihans')->where('statusBayar', 0)->count();
    });
  } catch (\Throwable $e) {
    $sidebarUnpaidCount = 0;
  }
}

// State aktif grup submenu
$isPetaMapping = request()->is('user/list/mapping') || request()->is('user/show/map') || request()->is('area*');
$isPerangkat   = request()->is('olt*') || request()->is('odc*') || request()->is('odp*');
$isPelangganList = request()->is('user') || (request()->is('user/*') && !request()->is('user/create') && !$isPetaMapping);
$isTagihanAktif  = request()->is('tagihan') || (request()->is('tagihan/*') && !request()->is('tagihan/lunas') && !request()->is('tagihan/rekap') && !request()->is('tagihan/generate'));
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
    <li class="menu-header fw-medium mt-2">
      <span class="menu-header-text">Utama</span>
    </li>
    <li class="menu-item {{ request()->is('/') ? 'active' : '' }}">
      <a href="{{ url('/') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-view-dashboard-outline"></i>
        <div>Dashboard</div>
      </a>
    </li>

    {{-- ================= TAGIHAN & KASIR (paling sering dipakai) ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 6, 7))
    <li class="menu-header fw-medium">
      <span class="menu-header-text">Tagihan & Kasir</span>
    </li>
    <li class="menu-item {{ $isTagihanAktif ? 'active' : '' }}">
      <a href="{{ route('tagihan.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-receipt-text-outline"></i>
        <div>Tagihan Aktif</div>
        @if($sidebarUnpaidCount > 0)
          <div class="badge rounded-pill bg-danger ms-auto sidebar-count-badge" title="{{ $sidebarUnpaidCount }} tagihan belum lunas">{{ $sidebarUnpaidCount > 99 ? '99+' : $sidebarUnpaidCount }}</div>
        @endif
      </a>
    </li>
    <li class="menu-item {{ request()->is('tagihan/lunas') ? 'active' : '' }}">
      <a href="{{ route('tagihan.lunas') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-check-decagram-outline"></i>
        <div>Riwayat Lunas</div>
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
        <i class="menu-icon tf-icons mdi mdi-lightning-bolt-outline"></i>
        <div>Generate Tagihan</div>
      </a>
    </li>
    @endif
    @endif

    {{-- ================= PELANGGAN ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 3, 4, 6, 7))
    <li class="menu-header fw-medium">
      <span class="menu-header-text">Pelanggan</span>
    </li>
    <li class="menu-item {{ $isPelangganList ? 'active' : '' }}">
      <a href="{{ route('user.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-account-group-outline"></i>
        <div>Daftar Pelanggan</div>
      </a>
    </li>

    @if($currentUser->hasRole(1, 2, 4, 7))
    @if($currentUser->hasRole(1, 2))
    <li class="menu-item {{ request()->is('user/create') ? 'active' : '' }}">
      <a href="{{ route('user.create') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-account-plus-outline"></i>
        <div>Tambah Pelanggan</div>
      </a>
    </li>
    @endif
    <li class="menu-item {{ $isPetaMapping ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons mdi mdi-map-search-outline"></i>
        <div>Peta & Mapping</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item {{ request()->is('user/list/mapping') ? 'active' : '' }}">
          <a href="{{ route('user.mapping') }}" class="menu-link">
            <div>Mapping Pelanggan</div>
          </a>
        </li>
        <li class="menu-item {{ request()->is('user/show/map') ? 'active' : '' }}">
          <a href="{{ route('user.show.mapping') }}" class="menu-link">
            <div>Lihat Map ODP</div>
          </a>
        </li>
        <li class="menu-item {{ request()->is('area*') ? 'active' : '' }}">
          <a href="{{ route('area.index') }}" class="menu-link">
            <div>Area Wilayah</div>
          </a>
        </li>
      </ul>
    </li>
    @endif
    @endif

    {{-- ================= JARINGAN ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2, 4, 7))
    <li class="menu-header fw-medium">
      <span class="menu-header-text">Jaringan</span>
    </li>

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
    @endif

    <li class="menu-item {{ $isPerangkat ? 'active open' : '' }}">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons mdi mdi-server-network"></i>
        <div>Perangkat</div>
      </a>
      <ul class="menu-sub">
        @if($currentUser->hasRole(1, 2))
        <li class="menu-item {{ request()->is('olt*') ? 'active' : '' }}">
          <a href="{{ route('olt.index') }}" class="menu-link">
            <div>OLT</div>
          </a>
        </li>
        @endif
        <li class="menu-item {{ request()->is('odc*') ? 'active' : '' }}">
          <a href="{{ route('odc.index') }}" class="menu-link">
            <div>ODC</div>
          </a>
        </li>
        <li class="menu-item {{ request()->is('odp*') ? 'active' : '' }}">
          <a href="{{ route('odp.index') }}" class="menu-link">
            <div>ODP</div>
          </a>
        </li>
      </ul>
    </li>

    <li class="menu-item">
      <a href="{{ route('google-map.standalone') }}" class="menu-link" target="_blank" rel="noopener">
        <i class="menu-icon tf-icons mdi mdi-map-marker-radius-outline"></i>
        <div>Peta Jaringan</div>
        <i class="mdi mdi-open-in-new ms-auto sidebar-external-icon" title="Buka di tab baru"></i>
      </a>
    </li>
    @endif

    {{-- ================= PAKET & LAYANAN ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2))
    <li class="menu-header fw-medium">
      <span class="menu-header-text">Paket & Layanan</span>
    </li>
    <li class="menu-item {{ request()->is('paket*') ? 'active' : '' }}">
      <a href="{{ route('paket.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-speedometer"></i>
        <div>Paket Internet</div>
      </a>
    </li>
    <li class="menu-item {{ request()->is('kategori*') ? 'active' : '' }}">
      <a href="{{ route('kategori.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-shape-outline"></i>
        <div>Kategori Paket</div>
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

    {{-- ================= SISTEM ================= --}}
    @if($currentUser && $currentUser->hasRole(1, 2))
    <li class="menu-header fw-medium">
      <span class="menu-header-text">Sistem</span>
    </li>
    @if($currentUser->isSuperAdmin())
    <li class="menu-item {{ request()->is('staff*') ? 'active' : '' }}">
      <a href="{{ route('staff.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-shield-account-outline"></i>
        <div>Manajemen Staf</div>
      </a>
    </li>
    @endif
    <li class="menu-item {{ request()->is('settings*') ? 'active' : '' }}">
      <a href="{{ route('settings.sync.index') }}" class="menu-link">
        <i class="menu-icon tf-icons mdi mdi-cog-sync-outline"></i>
        <div>Sinkronisasi Sistem</div>
      </a>
    </li>
    @endif

  </ul>

  @if($currentUser)
  {{-- ================= FOOTER: PROFIL, TEMA & LOGOUT =================
       Hanya desktop: navbar desktop dimatikan ($isNavbar = false), jadi di sinilah logout & ganti tema.
       Di HP/tablet (< 1200px) sudah ada di mobileNavbar kanan atas, jadi disembunyikan. --}}
  <div class="sidebar-user-footer border-top d-none d-xl-block">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div class="d-flex align-items-center gap-2 overflow-hidden">
        <div class="sidebar-user-avatar flex-shrink-0">
          {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
        </div>
        <div class="sidebar-user-meta text-truncate">
          <div class="fw-semibold text-truncate sidebar-user-name">{{ $currentUser->name ?? '-' }}</div>
          <small class="sidebar-user-role">{{ $currentUser->role_name ?? 'User' }}</small>
        </div>
      </div>
      <div class="sidebar-footer-actions d-flex align-items-center gap-1 flex-shrink-0">
        <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill"
          onclick="setAppTheme('{{ $isDark ? 'light' : 'dark' }}')"
          title="{{ $isDark ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap' }}">
          <i class="mdi {{ $isDark ? 'mdi-weather-sunny text-warning' : 'mdi-weather-night text-info' }} fs-5"></i>
        </button>
        <button type="button" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Keluar / Logout"
          onclick="document.getElementById('sidebar-logout-form').submit();">
          <i class="mdi mdi-power fs-5"></i>
        </button>
      </div>
    </div>
    <form method="POST" id="sidebar-logout-form" action="{{ route('logout') }}" class="d-none">
      @csrf
    </form>
  </div>
  @endif

</aside>
