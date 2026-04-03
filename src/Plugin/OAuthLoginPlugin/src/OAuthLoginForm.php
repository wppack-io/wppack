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

    public function renderButtons(): void
    {
        if ($this->providers === []) {
            return;
        }

        $redirectTo = $this->request->query->getString('redirect_to');
        $returnTo = $redirectTo !== '' ? wp_validate_redirect($redirectTo, admin_url()) : admin_url();
        $display = $this->config->buttonDisplay;
        $isInterim = $this->request->query->has('interim-login');
        $targetAttr = $isInterim ? ' target="_blank"' : '';

        $buttons = '';

        foreach ($this->providers as $provider) {
            $url = esc_url(add_query_arg([
                'return_to' => $returnTo,
            ], home_url($this->config->getAuthorizePath($provider->name))));
            $label = esc_html($provider->label);
            $icon = ProviderIcons::svg($provider->type) ?? ProviderIcons::svg($provider->name) ?? '';

            $color = null;
            if ($provider->buttonStyle !== null) {
                $color = ProviderIcons::style($provider->type, $provider->buttonStyle)
                    ?? ProviderIcons::style($provider->name, $provider->buttonStyle);
            }
            $color ??= ProviderIcons::brandColor($provider->type) ?? ProviderIcons::brandColor($provider->name) ?? ['bg' => '#f0f0f0', 'text' => '#1d2327'];
            $bg = esc_attr($color['bg']);
            $text = esc_attr($color['text']);
            $border = isset($color['border']) ? esc_attr($color['border']) : $bg;
            $iconColor = isset($color['icon']) && $color['icon'] !== 'original' ? 'color:' . esc_attr($color['icon']) . ';' : '';

            $showIcon = $display !== 'text-only' && $icon !== '';
            $showText = $display !== 'icon-only';

            $btnClass = 'wol-btn';
            $iconStyle = $iconColor !== '' ? ' style="' . $iconColor . '"' : '';
            $titleAttr = '';
            $iconSpan = $showIcon ? '<span class="wol-icon"' . $iconStyle . '>' . $icon . '</span>' : '';
            $label = esc_html(sprintf(__('Login with %s', 'wppack-oauth-login'), $provider->label));

            if ($display === 'icon-left' && $showIcon) {
                $btnClass .= ' wol-icon-left';
                $content = $iconSpan . ($showText ? '<span class="wol-text">' . $label . '</span>' : '');
            } elseif ($display === 'icon-only' && $showIcon) {
                $btnClass .= ' wol-icon-only';
                $titleAttr = ' title="' . esc_attr($label) . '"';
                $content = $iconSpan;
            } else {
                $content = $iconSpan . ($showText ? $label : '');
            }

            $buttons .= <<<HTML
                <a href="{$url}"{$titleAttr}{$targetAttr} class="{$btnClass}" style="background:{$bg};color:{$text};border-color:{$border};">{$content}</a>
            HTML;
        }

        $wrapStyle = $display === 'icon-only'
            ? 'display:flex;flex-wrap:wrap;gap:8px;'
            : 'display:flex;flex-direction:column;gap:8px;';

        echo <<<HTML
        <style>
        .wol-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;box-sizing:border-box;padding:0 12px;height:36px;border-radius:4px;border:1px solid;text-decoration:none;font-size:13px;font-weight:500;cursor:pointer;transition:filter .15s}
        .wol-btn:hover{filter:brightness(.92)}
        .wol-icon{display:inline-flex;width:20px;height:20px;flex:none}
        .wol-icon svg{width:100%;height:100%}
        .wol-text{flex:1;text-align:center}
        .wol-icon-left{gap:8px;padding-right:18px}
        .wol-icon-only{width:36px;padding:0}
        </style>
        <div id="wppack-oauth-login" style="display:none;clear:both;">
            <div style="display:flex;align-items:center;gap:8px;padding:16px 0;color:#72777c;"><span style="flex:1;border-top:1px solid #c3c4c7;"></span>or<span style="flex:1;border-top:1px solid #c3c4c7;"></span></div>
            <div style="{$wrapStyle}">{$buttons}</div>
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
