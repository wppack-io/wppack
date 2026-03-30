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

namespace WpPack\Plugin\OAuthLoginPlugin;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\OAuth\Assets\ProviderIcons;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WpPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;

class OAuthLoginForm
{
    /**
     * @param list<ProviderConfiguration> $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly OAuthLoginConfiguration $config,
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
    ) {}

    public function register(): void
    {
        add_action('login_init', [$this, 'redirectLoggedInUser']);
        add_action('login_footer', [$this, 'renderButtons']);
        add_filter('wp_login_errors', [$this, 'addOAuthError']);
    }

    public function redirectLoggedInUser(): void
    {
        if (!$this->authSession->isLoggedIn()
            || !$this->request->isMethod('GET')
            || $this->request->query->has('action')
            || $this->request->query->has('loggedout')
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

    public function renderButtons(): void
    {
        if ($this->providers === []) {
            return;
        }

        $redirectTo = $this->request->query->getString('redirect_to');
        $returnTo = $redirectTo !== '' ? wp_validate_redirect($redirectTo, admin_url()) : admin_url();
        $display = $this->config->buttonDisplay;

        $buttons = '';

        foreach ($this->providers as $provider) {
            $url = esc_url(add_query_arg([
                'return_to' => $returnTo,
            ], home_url($this->config->getAuthorizePath($provider->name))));
            $label = esc_html($provider->label);
            $icon = ProviderIcons::svg($provider->type) ?? ProviderIcons::svg($provider->name) ?? '';

            $color = ProviderIcons::brandColor($provider->type) ?? ProviderIcons::brandColor($provider->name) ?? ['bg' => '#f0f0f0', 'text' => '#1d2327'];
            $bg = esc_attr($color['bg']);
            $text = esc_attr($color['text']);
            $border = isset($color['border']) ? esc_attr($color['border']) : $bg;
            $iconColor = isset($color['icon']) && $color['icon'] !== 'original' ? 'color:' . esc_attr($color['icon']) . ';' : '';

            $showIcon = $display !== 'text-only' && $icon !== '';
            $showText = $display !== 'icon-only';

            if ($display === 'icon-left' && $showIcon) {
                $iconHtml = '<span style="position:absolute;left:12px;display:inline-flex;width:20px;height:20px;' . $iconColor . '">' . $icon . '</span>';
                $textHtml = $showText ? '<span style="flex:1;text-align:center;">' . esc_html(sprintf(__('%s でログイン', 'wppack-oauth-login'), $provider->label)) . '</span>' : '';
                $btnStyle = 'display:flex;align-items:center;position:relative;width:100%;box-sizing:border-box;padding:0 12px;height:40px;';
            } elseif ($display === 'icon-only' && $showIcon) {
                $iconHtml = '<span style="display:inline-flex;width:20px;height:20px;' . $iconColor . '">' . $icon . '</span>';
                $textHtml = '';
                $btnStyle = 'display:inline-flex;align-items:center;justify-content:center;width:48px;height:40px;';
            } else {
                $iconHtml = $showIcon ? '<span style="display:inline-flex;width:20px;height:20px;' . $iconColor . '">' . $icon . '</span>' : '';
                $textHtml = $showText ? esc_html(sprintf(__('%s でログイン', 'wppack-oauth-login'), $provider->label)) : '';
                $btnStyle = 'display:flex;align-items:center;justify-content:center;gap:8px;width:100%;box-sizing:border-box;padding:0 12px;height:40px;';
            }

            $titleAttr = $display === 'icon-only' ? ' title="' . esc_attr(sprintf(__('%s でログイン', 'wppack-oauth-login'), $provider->label)) . '"' : '';

            $buttons .= <<<HTML
                <p>
                    <a href="{$url}"{$titleAttr} style="{$btnStyle}border-radius:4px;background:{$bg};color:{$text};border:1px solid {$border};text-decoration:none;font-size:14px;font-weight:500;cursor:pointer;">{$iconHtml}{$textHtml}</a>
                </p>
            HTML;
        }

        echo <<<HTML
        <style>#wppack-oauth-login a{transition:filter .15s}#wppack-oauth-login a:hover{filter:brightness(.92)}</style>
        <div id="wppack-oauth-login" style="display:none;clear:both;">
            <div style="display:flex;align-items:center;gap:8px;padding:16px 0;color:#72777c;"><span style="flex:1;border-top:1px solid #c3c4c7;"></span>or<span style="flex:1;border-top:1px solid #c3c4c7;"></span></div>
            {$buttons}
        </div>
        <script>
        (function(){
            var ssoBox=document.getElementById('wppack-oauth-login');
            var loginForm=document.getElementById('loginform');
            if(ssoBox&&loginForm){
                loginForm.appendChild(ssoBox);
                ssoBox.style.display='';
            }
        })();
        </script>
        HTML;
    }

    public function addOAuthError(\WP_Error $errors): \WP_Error
    {
        if ($this->request->query->has('oauth_error')) {
            $errors->add('oauth_error', 'OAuth authentication failed. Please try again.');
        }

        return $errors;
    }
}
