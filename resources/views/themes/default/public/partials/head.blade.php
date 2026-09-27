@php
  use App\Models\Setting;

  $siteName   = Setting::get('site_name', config('app.name', 'Lumora Hosting'));
  $pageTitle  = $seoTitle ?? $siteName;
  $pageDesc   = $seoDescription ?? Setting::get('seo_description', '');
  $keywords   = $seoKeywords ?? Setting::get('seo_keywords', '');
  $ogImage    = $seoImage ?? Setting::get('seo_og_image', '');
  $canonical  = $seoCanonical ?? url()->current();

  // noindex bisa berasal dari setelan seluruh situs atau per halaman.
  $noindex    = ($seoNoindex ?? false) || Setting::get('seo_noindex_site') === '1';

  $gaId       = Setting::get('ga_measurement_id');
  $gtmId      = Setting::get('gtm_container_id');
  $fbPixel    = Setting::get('fb_pixel_id');
@endphp

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $pageTitle }}{{ $pageTitle !== $siteName ? ' — ' . $siteName : '' }}</title>

@if ($pageDesc)
  <meta name="description" content="{{ $pageDesc }}">
@endif
@if ($keywords)
  <meta name="keywords" content="{{ $keywords }}">
@endif

@if ($noindex)
  <meta name="robots" content="noindex, nofollow">
@else
  <meta name="robots" content="index, follow">
@endif

<link rel="canonical" href="{{ $canonical }}">

{{-- Open Graph --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $pageTitle }}">
@if ($pageDesc)
  <meta property="og:description" content="{{ $pageDesc }}">
@endif
@if ($ogImage)
  <meta property="og:image" content="{{ $ogImage }}">
@endif
<meta property="og:url" content="{{ $canonical }}">

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
@if ($pageDesc)
  <meta name="twitter:description" content="{{ $pageDesc }}">
@endif
@if ($ogImage)
  <meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- Analytics: hanya ID yang disimpan, script-nya dibangun di sini,
     jadi tidak ada HTML mentah dari database yang dieksekusi. --}}
@if ($gtmId)
  <script>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
    var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
    j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');
  </script>
@endif

@if ($gaId)
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $gaId }}');
  </script>
@endif

@if ($fbPixel)
  <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $fbPixel }}');
    fbq('track', 'PageView');
  </script>
@endif
