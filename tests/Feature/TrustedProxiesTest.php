<?php

test('the application trusts reverse proxy headers', function () {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)->toContain("trustProxies(at: '*')");
});
