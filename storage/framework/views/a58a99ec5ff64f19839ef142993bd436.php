<?php
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
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

<title><?php echo e($pageTitle); ?><?php echo e($pageTitle !== $siteName ? ' — ' . $siteName : ''); ?></title>

<?php if($pageDesc): ?>
  <meta name="description" content="<?php echo e($pageDesc); ?>">
<?php endif; ?>
<?php if($keywords): ?>
  <meta name="keywords" content="<?php echo e($keywords); ?>">
<?php endif; ?>

<?php if($noindex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php else: ?>
  <meta name="robots" content="index, follow">
<?php endif; ?>

<link rel="canonical" href="<?php echo e($canonical); ?>">


<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo e($siteName); ?>">
<meta property="og:title" content="<?php echo e($pageTitle); ?>">
<?php if($pageDesc): ?>
  <meta property="og:description" content="<?php echo e($pageDesc); ?>">
<?php endif; ?>
<?php if($ogImage): ?>
  <meta property="og:image" content="<?php echo e($ogImage); ?>">
<?php endif; ?>
<meta property="og:url" content="<?php echo e($canonical); ?>">


<meta name="twitter:card" content="<?php echo e($ogImage ? 'summary_large_image' : 'summary'); ?>">
<meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
<?php if($pageDesc): ?>
  <meta name="twitter:description" content="<?php echo e($pageDesc); ?>">
<?php endif; ?>
<?php if($ogImage): ?>
  <meta name="twitter:image" content="<?php echo e($ogImage); ?>">
<?php endif; ?>


<?php if($gtmId): ?>
  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
    var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
    j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo e($gtmId); ?>');
  </script>
<?php endif; ?>

<?php if($gaId): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e($gaId); ?>"></script>
  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo e($gaId); ?>');
  </script>
<?php endif; ?>

<?php if($fbPixel): ?>
  <script <?php echo 'nonce="' . e(app(\App\Support\CspNonce::class)->value()) . '"'; ?>>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo e($fbPixel); ?>');
    fbq('track', 'PageView');
  </script>
<?php endif; ?>
<?php /**PATH /home/runner/workspace/whmku4/whmku4/resources/views/themes/public-themes/namahost/public/partials/head.blade.php ENDPATH**/ ?>