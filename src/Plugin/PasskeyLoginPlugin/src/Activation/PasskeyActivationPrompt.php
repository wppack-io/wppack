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

namespace WpPack\Plugin\PasskeyLoginPlugin\Activation;

use WpPack\Component\Transient\TransientManager;

/**
 * Shows a passkey registration prompt on wp-activate.php after successful user activation.
 *
 * Uses a one-time activation token (not login) so the user stays unauthenticated.
 */
final class PasskeyActivationPrompt
{
    private const TOKEN_PREFIX = 'wppack_passkey_activate_';
    private const TOKEN_TTL = 600;

    private ?int $activatedUserId = null;
    private ?string $activationToken = null;

    public function __construct(
        private readonly TransientManager $transients,
    ) {}

    public function register(): void
    {
        add_action('wpmu_activate_user', [$this, 'onUserActivated'], 10, 3);
        add_action('wp_footer', [$this, 'renderPrompt']);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function onUserActivated(int $userId, string $password, array $meta): void
    {
        $this->activatedUserId = $userId;

        // Generate a one-time activation token (no login required)
        $token = bin2hex(random_bytes(32));
        $this->activationToken = $token;
        $this->transients->set(self::TOKEN_PREFIX . $token, $userId, self::TOKEN_TTL);
    }

    /**
     * Validate an activation token without consuming it.
     */
    public function validateToken(string $token): ?int
    {
        $userId = $this->transients->get(self::TOKEN_PREFIX . $token);

        return \is_int($userId) ? $userId : null;
    }

    /**
     * Consume an activation token and return the user ID if valid.
     */
    public function consumeToken(string $token): ?int
    {
        $key = self::TOKEN_PREFIX . $token;
        $userId = $this->transients->get($key);

        if (!\is_int($userId)) {
            return null;
        }

        $this->transients->delete($key);

        return $userId;
    }

    public function renderPrompt(): void
    {
        if ($this->activatedUserId === null || $this->activationToken === null) {
            return;
        }

        $loginUrl = esc_url(wp_login_url());

        $config = wp_json_encode([
            'api' => rest_url('wppack/v1/passkey'),
            'activationToken' => $this->activationToken,
            'loginUrl' => wp_login_url(),
            'msgSuccess' => __('Passkey registered! You can now log in with your passkey.', 'wppack-passkey-login'),
            'msgFail' => __('Passkey registration failed. You can add a passkey later from your profile.', 'wppack-passkey-login'),
            'msgCancelled' => __('You can add a passkey later from your profile.', 'wppack-passkey-login'),
            'loginLabel' => __('Log in', 'wppack-passkey-login'),
        ]);

        $setupLabel = esc_html(__('Add Passkey', 'wppack-passkey-login'));
        $skipLabel = esc_html(__('Skip', 'wppack-passkey-login'));
        $heading = esc_html(__('Passkey', 'wppack-passkey-login'));
        $description = esc_html(__('Add a passkey to sign in quickly and securely next time.', 'wppack-passkey-login'));

        echo <<<HTML
        <div id="wppack-passkey-activate" style="display:none;margin-top:2em;text-align:center;">
            <hr style="margin-bottom:2em;">
            <h3>{$heading}</h3>
            <p>{$description}</p>
            <p>
                <button type="button" id="wppack-passkey-activate-btn" class="button button-primary" style="margin-right:8px;">{$setupLabel}</button>
                <a href="{$loginUrl}" id="wppack-passkey-activate-skip" class="button">{$skipLabel}</a>
            </p>
            <p id="wppack-passkey-activate-msg" style="display:none;margin-top:1em;"></p>
        </div>
        <script>
        (function(){
            var prompt=document.getElementById('wppack-passkey-activate');
            var container=document.querySelector('.wp-activate-container');
            if(prompt&&container){container.appendChild(prompt);prompt.style.display='';}
            else if(prompt){prompt.style.display='';}

            var C={$config};
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
            function showMsg(text,ok){
                var el=document.getElementById('wppack-passkey-activate-msg');
                el.textContent=text;
                el.style.color=ok?'#00a32a':'#d63638';
                el.style.display='';
            }
            function api(path,body){
                return fetch(C.api+path,{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify(body)
                }).then(function(r){return r.json()});
            }
            var btn=document.getElementById('wppack-passkey-activate-btn');
            if(!btn||!window.PublicKeyCredential)return;
            btn.addEventListener('click',function(){
                btn.disabled=true;
                api('/activate/options',{activationToken:C.activationToken}).then(function(opts){
                    if(opts.error){throw new Error(opts.error);}
                    var challengeKey=opts.challengeKey;
                    var pk={
                        challenge:b64urlDec(opts.challenge),
                        rp:opts.rp,
                        user:{...opts.user,id:b64urlDec(opts.user.id)},
                        pubKeyCredParams:opts.pubKeyCredParams,
                        authenticatorSelection:opts.authenticatorSelection,
                        attestation:opts.attestation,
                        timeout:opts.timeout
                    };
                    if(opts.excludeCredentials){
                        pk.excludeCredentials=opts.excludeCredentials.map(function(c){
                            return{type:c.type,id:b64urlDec(c.id),transports:c.transports};
                        });
                    }
                    return navigator.credentials.create({publicKey:pk}).then(function(cred){
                        var body={
                            id:cred.id,
                            rawId:b64url(cred.rawId),
                            type:cred.type,
                            challengeKey:challengeKey,
                            activationToken:C.activationToken,
                            response:{
                                attestationObject:b64url(cred.response.attestationObject),
                                clientDataJSON:b64url(cred.response.clientDataJSON)
                            }
                        };
                        if(cred.response.getTransports)body.response.transports=cred.response.getTransports();
                        return api('/activate/verify',body);
                    });
                }).then(function(result){
                    if(result&&result.success){
                        showMsg(C.msgSuccess,true);
                        btn.style.display='none';
                        var skip=document.getElementById('wppack-passkey-activate-skip');
                        if(skip){skip.textContent=C.loginLabel;skip.className='button button-primary';}
                    }else{
                        showMsg(result&&result.error?result.error:C.msgFail,false);
                        btn.disabled=false;
                    }
                }).catch(function(e){
                    if(e.name==='NotAllowedError'){
                        showMsg(C.msgCancelled,false);
                    }else{
                        showMsg(C.msgFail,false);
                    }
                    btn.disabled=false;
                });
            });
        })();
        </script>
        HTML;
    }
}
