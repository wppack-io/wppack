<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Plugin\SamlLoginPlugin;

use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\SAML\SamlEntryPoint;

final class SamlLoginForm
{
    private bool $ssoOnly = false;

    public function __construct(
        private readonly SamlEntryPoint $entryPoint,
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
    ) {}

    public function register(bool $ssoOnly = false): void
    {
        $this->ssoOnly = $ssoOnly;
        add_action('login_init', [$this, 'redirectLoggedInUser']);
        add_action('login_footer', [$this, 'renderButton']);
        add_filter('wp_login_errors', [$this, 'addSamlError']);
    }

    public function redirectLoggedInUser(): void
    {
        if (!$this->authSession->isLoggedIn()
            || !$this->request->isMethod('GET')
            || $this->request->query->has('action')
            || $this->request->query->has('loggedout')
            || $this->request->query->has('interim-login')
        ) {
            return;
        }

        $redirectTo = $this->request->query->getString('redirect_to');
        $destination = $redirectTo !== ''
            ? wp_validate_redirect($redirectTo, admin_url())
            : admin_url();
        wp_safe_redirect($destination);
        exit;
    }

    public function renderButton(): void
    {
        $redirectTo = $this->request->query->getString('redirect_to');
        $returnTo = $redirectTo !== '' ? $redirectTo : admin_url();
        $url = esc_url($this->entryPoint->getLoginUrl($returnTo));
        $label = esc_html(sprintf(__('Login with %s', 'wppack-saml-login'), 'SSO'));
        $target = $this->request->query->has('interim-login') ? ' target="_blank"' : '';

        $or = esc_html(__('or', 'wppack-saml-login'));
        $separator = $this->ssoOnly ? '' : '<div style="display:flex;align-items:center;gap:8px;padding:16px 0;color:#72777c;"><span style="flex:1;border-top:1px solid #c3c4c7;"></span>' . $or . '<span style="flex:1;border-top:1px solid #c3c4c7;"></span></div>';

        echo <<<HTML
        <div id="wppack-saml-login" style="display:none;clear:both;">
            {$separator}
            <p>
                <a href="{$url}"{$target} style="display:flex;align-items:center;justify-content:center;width:100%;height:36px;box-sizing:border-box;border-radius:4px;background:#fff;color:#1d2327;border:1px solid #ddd;text-decoration:none;font-size:13px;font-weight:500;cursor:pointer;transition:filter .15s;"
                   onmouseover="this.style.filter='brightness(.92)'" onmouseout="this.style.filter=''">{$label}</a>
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
            if({$this->ssoOnlyJs()}&&loginForm){
                Array.from(loginForm.children).forEach(function(el){
                    if(el.id!=='wppack-saml-login')el.style.display='none';
                });
            }
        })();
        </script>
        HTML;
    }

    private function ssoOnlyJs(): string
    {
        return $this->ssoOnly ? 'true' : 'false';
    }

    public function addSamlError(\WP_Error $errors): \WP_Error
    {
        if ($this->request->query->has('saml_error')) {
            $errors->add('saml_error', 'SAML authentication failed. Please try again.');
        }

        return $errors;
    }
}
