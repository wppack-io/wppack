<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Plugin\SamlLoginPlugin;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Bridge\SAML\SamlEntryPoint;

final class SamlLoginForm
{
    public function __construct(
        private readonly SamlEntryPoint $entryPoint,
        private readonly Request $request,
    ) {}

    public function register(): void
    {
        add_action('login_footer', [$this, 'renderButton']);
        add_filter('login_message', [$this, 'renderErrorMessage']);
    }

    public function renderButton(): void
    {
        $redirectTo = $this->request->query->getString('redirect_to');
        $returnTo = $redirectTo !== '' ? $redirectTo : admin_url();
        $url = esc_url($this->entryPoint->getLoginUrl($returnTo));

        echo <<<HTML
        <div id="wppack-saml-login" style="display:none;clear:both;">
            <div style="display:flex;align-items:center;gap:8px;padding:16px 0;color:#72777c;"><span style="flex:1;border-top:1px solid #c3c4c7;"></span>or<span style="flex:1;border-top:1px solid #c3c4c7;"></span></div>
            <p>
                <a href="{$url}" class="button button-large" style="width:100%;text-align:center;box-sizing:border-box;">Login with SSO</a>
            </p>
        </div>
        <script>
        (function(){
            var ssoBox=document.getElementById('wppack-saml-login');
            var loginForm=document.getElementById('loginform');
            if(ssoBox&&loginForm){
                loginForm.appendChild(ssoBox);
                ssoBox.style.display='';
            }
        })();
        </script>
        HTML;
    }

    public function renderErrorMessage(string $message): string
    {
        if ($this->request->query->getString('action') !== 'saml_error') {
            return $message;
        }

        return '<div id="login_error">SAML authentication failed. Please try again.</div>' . $message;
    }
}
