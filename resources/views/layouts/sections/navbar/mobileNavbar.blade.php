@php
$currentUser = auth()->user();
@endphp

<!-- Mobile Navbar (HANYA tampil di layar < 1200px / Smartphone & Tablet) -->
<nav class="mobile-navbar d-xl-none bg-white border-bottom sticky-top py-2 px-3 shadow-xs" style="z-index: 1025;">
  <div class="d-flex align-items-center justify-content-between w-100">
    <div class="d-flex align-items-center gap-2">
      <a href="javascript:void(0);" class="layout-menu-toggle btn btn-sm btn-icon btn-text-secondary rounded-pill me-1" title="Menu Navigasi" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">
        <i class="mdi mdi-menu fs-3"></i>
      </a>
      <a href="{{ url('/') }}" class="d-flex align-items-center text-decoration-none text-dark gap-2">
        <span class="badge bg-primary p-1 rounded-2 shadow-xs">
          <i class="mdi mdi-access-point-network text-white fs-6"></i>
        </span>
        <span class="fw-bold fs-6 tracking-tight">MRYES NET</span>
      </a>
    </div>

    <div class="d-flex align-items-center gap-2">
      <!-- Theme Switcher (Mobile 1-Tap) -->
      @php
        $currentStyleVal = $configData['style'] ?? 'light';
        $nextStyleVal = ($currentStyleVal === 'dark') ? 'light' : 'dark';
      @endphp
      <a href="javascript:void(0);" onclick="setAppTheme('{{ $nextStyleVal }}')" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Ganti Mode Tema" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;">
        <i class="mdi {{ ($currentStyleVal === 'dark') ? 'mdi-weather-night text-info' : 'mdi-weather-sunny text-warning' }} fs-5"></i>
      </a>

      @if($currentUser)
      <div class="dropdown">
        <a href="javascript:void(0);" class="d-flex align-items-center gap-1 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-xs" style="width: 32px; height: 32px; background: linear-gradient(135deg, #2563eb, #0ea5e9); font-size: 0.8rem;">
            {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow border py-2" style="border-radius: 12px; min-width: 210px;">
          <li class="px-3 py-2 mb-1 bg-light-subtle border-bottom">
            <div class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;">{{ $currentUser->name }}</div>
            <small class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;">{{ $currentUser->role_name }}</small>
          </li>
          <li>
            <a class="dropdown-item py-2 text-dark" href="{{ route('pages-home') }}">
              <i class="mdi mdi-view-dashboard-outline me-2 text-primary"></i> Dashboard
            </a>
          </li>
          @if($currentUser->hasRole(1, 2, 4, 7))
          <li>
            <a class="dropdown-item py-2 text-dark" href="{{ route('user.show.mapping') }}">
              <i class="mdi mdi-map-marker-distance me-2 text-info"></i> Peta ODP
            </a>
          </li>
          <li>
            <a class="dropdown-item py-2 text-dark" href="{{ route('user.create') }}">
              <i class="mdi mdi-account-plus me-2 text-warning"></i> Tambah Pelanggan
            </a>
          </li>
          @endif
          @if($currentUser->hasRole(1, 2, 3, 6, 7))
          <li>
            <a class="dropdown-item py-2 text-dark" href="{{ route('tagihan.index') }}">
              <i class="mdi mdi-receipt-text-outline me-2 text-success"></i> Tagihan Aktif
            </a>
          </li>
          @endif
          <li><hr class="dropdown-divider my-1"></li>
          <li>
            <a class="dropdown-item py-2 text-danger fw-semibold" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('mobile-logout-form').submit();">
              <i class="mdi mdi-logout me-2"></i> Keluar / Logout
            </a>
          </li>
          <form id="mobile-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
          </form>
        </ul>
      </div>
      @endif
    </div>
  </div>
</nav>

<script>
  if (typeof setAppTheme !== 'function') {
    function setAppTheme(theme) {
      if (theme === 'system') {
        const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        theme = isDark ? 'dark' : 'light';
      }
      document.cookie = "style=" + theme + ";path=/;max-age=31536000;SameSite=Lax";
      try {
        localStorage.setItem('templateCustomizer--Style', theme);
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          if (k && k.includes('--Style')) localStorage.setItem(k, theme);
        }
      } catch (e) {}
      window.location.reload();
    }
  }
</script>

