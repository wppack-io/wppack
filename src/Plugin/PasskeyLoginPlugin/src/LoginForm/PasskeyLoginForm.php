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

namespace WpPack\Plugin\PasskeyLoginPlugin\LoginForm;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

final class PasskeyLoginForm
{
    public function __construct(
        private readonly AuthenticationSession $authSession,
        private readonly Request $request,
        private readonly PasskeyLoginConfiguration $config,
    ) {}

    public function register(): void
    {
        add_action('login_init', [$this, 'redirectLoggedInUser']);
        add_action('login_form', [$this, 'addConditionalUiAttributes']);
        add_action('login_footer', [$this, 'renderButton']);
        add_filter('wp_login_errors', [$this, 'addPasskeyError']);
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

    /**
     * Add autocomplete="username webauthn" to the username field for Conditional UI.
     */
    public function addConditionalUiAttributes(): void
    {
        echo <<<'HTML'
        <script>
        (function(){
            var u=document.getElementById('user_login');
            if(u)u.setAttribute('autocomplete','username webauthn');
        })();
        </script>
        HTML;
    }

    public function renderButton(): void
    {
        $restUrl = esc_url(rest_url('wppack/v1/passkey'));
        $redirectTo = $this->request->query->getString('redirect_to');
        $returnTo = esc_js($redirectTo !== '' ? wp_validate_redirect($redirectTo, admin_url()) : admin_url());
        $label = esc_html(__('Log in with Passkey', 'wppack-passkey-login'));
        $or = esc_html(__('or', 'wppack-passkey-login'));
        $nonce = esc_js(wp_create_nonce('wp_rest'));
        $display = $this->config->buttonDisplay;

        // Translatable error messages for inline JS
        $errCancelled = esc_js(__('Passkey authentication was cancelled.', 'wppack-passkey-login'));
        $errFailed = esc_js(__('Passkey authentication failed.', 'wppack-passkey-login'));

        // Build button — same structure as OAuthLoginForm (wpl-btn/wpl-icon/wpl-text/wpl-icon-left/wpl-icon-only)
        $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/></svg>';

        $showIcon = $display !== 'text-only';
        $showText = $display !== 'icon-only';
        $iconSpan = $showIcon ? '<span class="wpl-icon">' . $iconSvg . '</span>' : '';

        $btnClass = 'wpl-btn';
        $titleAttr = '';

        if ($display === 'icon-left' && $showIcon) {
            $btnClass .= ' wpl-icon-left';
            $btnContent = $iconSpan . ($showText ? '<span class="wpl-text">' . $label . '</span>' : '');
        } elseif ($display === 'icon-only' && $showIcon) {
            $btnClass .= ' wpl-icon-only';
            $titleAttr = ' title="' . $label . '"';
            $btnContent = $iconSpan;
        } else {
            $btnContent = $iconSpan . ($showText ? $label : '');
        }

        echo <<<HTML
        <style>
        .wpl-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;box-sizing:border-box;padding:0 12px;height:36px;border-radius:4px;background:#fff;color:#1d2327;border:1px solid #ddd;text-decoration:none;font-size:13px;font-weight:500;cursor:pointer;transition:filter .15s}
        .wpl-btn:hover{filter:brightness(.92)}
        .wpl-icon{display:inline-flex;width:20px;height:20px;flex:none}
        .wpl-icon svg{width:100%;height:100%}
        .wpl-text{flex:1;text-align:center}
        .wpl-icon-left{gap:8px;padding-right:18px}
        .wpl-icon-only{width:36px;padding:0}
        </style>
        <div id="wppack-passkey-login" style="display:none;clear:both;">
            <div style="display:flex;align-items:center;gap:8px;padding:16px 0;color:#72777c;"><span style="flex:1;border-top:1px solid #c3c4c7;"></span>{$or}<span style="flex:1;border-top:1px solid #c3c4c7;"></span></div>
            <button type="button" id="wppack-passkey-btn" class="{$btnClass}"{$titleAttr}>{$btnContent}</button>
            <p id="wppack-passkey-error" style="display:none;color:#d63638;font-size:13px;text-align:center;margin-top:8px;"></p>
        </div>
        <script>
        (function(){
            var API='{$restUrl}';
            var REDIRECT='{$returnTo}';
            var NONCE='{$nonce}';
            var ERR_CANCELLED='{$errCancelled}';
            var ERR_FAILED='{$errFailed}';
            var ssoBox=document.getElementById('wppack-passkey-login');
            var loginForm=document.getElementById('loginform');
            if(ssoBox&&loginForm){
                loginForm.appendChild(ssoBox);
                ssoBox.style.display='';
            }

            function b64url(buf){
                var s='',a=new Uint8Array(buf);
                for(var i=0;i<a.length;i++)s+=String.fromCharCode(a[i]);
                return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
            }
            function b64urlDec(s){
                s=s.replace(/-/g,'+').replace(/_/g,'/');
                while(s.length%4)s+='=';
                var b=atob(s),a=new Uint8Array(b.length);
                for(var i=0;i<b.length;i++)a[i]=b.charCodeAt(i);
                return a.buffer;
            }

            function fetchOptions(){
                return fetch(API+'/authenticate/options',{
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},
                    credentials:'same-origin'
                }).then(function(r){return r.json()});
            }

            function verifyAssertion(assertion,challengeKey){
                var body={
                    id:assertion.id,
                    rawId:b64url(assertion.rawId),
                    type:assertion.type,
                    challengeKey:challengeKey,
                    response:{
                        authenticatorData:b64url(assertion.response.authenticatorData),
                        clientDataJSON:b64url(assertion.response.clientDataJSON),
                        signature:b64url(assertion.response.signature)
                    }
                };
                if(assertion.response.userHandle){
                    body.response.userHandle=b64url(assertion.response.userHandle);
                }
                return fetch(API+'/authenticate/verify',{
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},
                    credentials:'same-origin',
                    body:JSON.stringify(body)
                }).then(function(r){return r.json()});
            }

            function showError(msg){
                var el=document.getElementById('wppack-passkey-error');
                if(el){el.textContent=msg;el.style.display='';}
            }

            // AbortController to cancel conditional UI when button is clicked
            var conditionalAbort=null;

            function handleAssertion(opts,mediation,signal){
                var challengeKey=opts.challengeKey||'';
                var challenge=opts.challenge||'';
                var allowCreds=(opts.allowCredentials||[]).map(function(c){
                    return{type:c.type,id:b64urlDec(c.id),transports:c.transports};
                });
                var pubKey={
                    challenge:b64urlDec(challenge),
                    rpId:opts.rpId,
                    timeout:opts.timeout||60000,
                    userVerification:opts.userVerification||'preferred'
                };
                if(allowCreds.length)pubKey.allowCredentials=allowCreds;
                var credOpts={publicKey:pubKey};
                if(mediation)credOpts.mediation=mediation;
                if(signal)credOpts.signal=signal;
                return navigator.credentials.get(credOpts).then(function(cred){
                    return verifyAssertion(cred,challengeKey);
                }).then(function(result){
                    if(result.success){
                        window.location.href=result.redirectUrl||REDIRECT;
                    }else{
                        showError(result.error||ERR_FAILED);
                    }
                });
            }

            // Modal mode: button click
            var btn=document.getElementById('wppack-passkey-btn');
            if(btn){
                btn.addEventListener('click',function(){
                    // Abort conditional UI before starting modal
                    if(conditionalAbort){conditionalAbort.abort();conditionalAbort=null;}
                    btn.disabled=true;
                    fetchOptions().then(function(opts){
                        return handleAssertion(opts);
                    }).catch(function(e){
                        if(e.name==='AbortError')return;
                        showError(e.name==='NotAllowedError'?ERR_CANCELLED:ERR_FAILED);
                    }).finally(function(){btn.disabled=false;});
                });
            }

            // Conditional UI: autofill-assisted passkey selection
            if(window.PublicKeyCredential&&typeof PublicKeyCredential.isConditionalMediationAvailable==='function'){
                PublicKeyCredential.isConditionalMediationAvailable().then(function(ok){
                    if(!ok)return;
                    conditionalAbort=new AbortController();
                    fetchOptions().then(function(opts){
                        return handleAssertion(opts,'conditional',conditionalAbort.signal);
                    }).catch(function(){/* user did not select a passkey via autofill, or aborted */});
                });
            }
        })();
        </script>
        HTML;
    }

    public function addPasskeyError(\WP_Error $errors): \WP_Error
    {
        if ($this->request->query->has('passkey_error')) {
            $errors->add('passkey_error', __('Passkey authentication failed. Please try again.', 'wppack-passkey-login'));
        }

        return $errors;
    }
}
