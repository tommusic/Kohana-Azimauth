<?
    $config = Kohana::config('azimauth');
?>
<a class="rpxnow" onclick="return false;"
    href="<?= $config['rpx_domain']; ?>/openid/v2/signin?token_url=<?= $config['rpx_token_url']; ?>">Sign In</a>