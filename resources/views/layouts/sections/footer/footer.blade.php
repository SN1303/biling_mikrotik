@php
$containerFooter = ($configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-3 flex-md-row flex-column">
      <div class="mb-2 mb-md-0 text-muted" style="font-size: 0.8125rem;">
        &copy; <script>document.write(new Date().getFullYear())</script>
        <strong class="text-dark">MRYES NET</strong> — Sistem Billing & Manajemen Jaringan MikroTik
      </div>
    </div>
  </div>
</footer>
<!--/ Footer-->
