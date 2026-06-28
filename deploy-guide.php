<?php
/**
 * Apex Cybernet — Deploy setup guide (internal)
 *
 * Static how-to. Contains NO secrets: the two secret values (deploy key +
 * webhook secret) are pasted in by the operator at run time.
 *
 * Delete from the live site after setup:  rm /var/www/apexcybernet/deploy-guide.php
 */
header('X-Robots-Tag: noindex, nofollow', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Apex Cybernet — Deploy Setup Guide</title>
<style>
  :root{
    --bg:#0f0f13; --panel:#17171d; --panel2:#1d1d25; --line:#2a2a35;
    --text:#e7e7ee; --muted:#9a9aab; --accent:#7c3aed; --accent2:#a78bfa;
    --green:#34d399; --amber:#fbbf24; --red:#f87171; --code:#0b0b0f;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);
    font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
  .wrap{max-width:880px;margin:0 auto;padding:2.5rem 1.25rem 5rem;}
  h1{font-size:1.8rem;margin:0 0 .25rem;letter-spacing:-.5px;}
  h1 .accent{color:var(--accent2);}
  .sub{color:var(--muted);margin:0 0 1.5rem;}
  .banner{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.35);
    color:#fde68a;border-radius:10px;padding:.85rem 1rem;margin:0 0 1.75rem;font-size:.9rem;}
  .banner b{color:var(--amber);}
  .meta{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 2rem;}
  .pill{background:var(--panel2);border:1px solid var(--line);border-radius:999px;
    padding:.3rem .8rem;font-size:.8rem;color:var(--muted);}
  .pill b{color:var(--text);}
  .step{background:var(--panel);border:1px solid var(--line);border-radius:14px;
    padding:1.25rem 1.35rem;margin:0 0 1.1rem;}
  .step h2{display:flex;align-items:center;gap:.6rem;margin:0 0 .5rem;font-size:1.05rem;}
  .num{flex:0 0 1.7rem;height:1.7rem;border-radius:8px;background:var(--accent);
    color:#fff;display:grid;place-items:center;font-size:.85rem;font-weight:700;}
  .step p{color:var(--muted);margin:.25rem 0 .9rem;font-size:.92rem;}
  .where{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.3px;
    text-transform:uppercase;padding:.12rem .5rem;border-radius:6px;margin-left:.4rem;}
  .where.vps{background:rgba(52,211,153,.12);color:var(--green);border:1px solid rgba(52,211,153,.3);}
  .where.gh{background:rgba(124,58,237,.14);color:var(--accent2);border:1px solid rgba(124,58,237,.35);}
  .cmd{position:relative;background:var(--code);border:1px solid var(--line);
    border-radius:10px;margin:.6rem 0;}
  .cmd pre{margin:0;padding:.9rem 3.2rem .9rem 1rem;overflow-x:auto;
    font:13px/1.55 "SFMono-Regular",Consolas,"Liberation Mono",monospace;color:#d6d6e0;}
  .cmd .tok{color:var(--accent2);}            /* placeholders */
  .cmd .cmt{color:#6b7280;}                    /* comments */
  .copy{position:absolute;top:.55rem;right:.55rem;background:var(--panel2);
    border:1px solid var(--line);color:var(--muted);border-radius:7px;
    padding:.3rem .55rem;font-size:.72rem;cursor:pointer;transition:.15s;}
  .copy:hover{color:var(--text);border-color:var(--accent);}
  .copy.done{color:var(--green);border-color:var(--green);}
  .note{font-size:.85rem;color:var(--muted);border-left:2px solid var(--accent);
    padding-left:.75rem;margin:.7rem 0;}
  .note.warn{border-color:var(--amber);}
  code.inl{background:var(--panel2);border:1px solid var(--line);border-radius:5px;
    padding:.05rem .35rem;font-size:.85em;color:var(--accent2);}
  .ok{color:var(--green);} .danger{color:var(--red);}
  a{color:var(--accent2);}
  footer{color:var(--muted);font-size:.82rem;text-align:center;margin-top:2.5rem;}
</style>
</head>
<body>
<div class="wrap">

  <h1>Apex <span class="accent">Cybernet</span> — Deploy Setup</h1>
  <p class="sub">One-time provisioning of git auto-deploy + Apache + SSL + DB on the existing VPS.</p>

  <div class="banner">
    <b>Internal doc.</b> Run these on the VPS as <b>root</b>. Two steps need a secret value
    pasted in (shown as <span class="tok">&lt;PASTE …&gt;</span>) — get those from the chat.
    Delete this page from the live site when finished (last step).
  </div>

  <div class="meta">
    <span class="pill">Domain: <b>apexcybernet.com</b></span>
    <span class="pill">Web root: <b>/var/www/apexcybernet</b></span>
    <span class="pill">Repo: <b>argonarsoftware-oss/apexcybernet.com</b></span>
    <span class="pill">Branch: <b>main</b> (deploy on <b>[deploy]</b>)</span>
    <span class="pill ok">✓ webhook + deploy key already created on GitHub</span>
  </div>

  <!-- STEP 1 -->
  <div class="step">
    <h2><span class="num">1</span> Config dir, deploy key & webhook secret <span class="where vps">on VPS</span></h2>
    <p>Stores the deploy key and webhook secret outside the web root. Paste the
       <b>deploy key</b> and <b>webhook secret</b> from the chat where marked.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre><span class="cmt"># config dir (root-only)</span>
install -d -m 700 /etc/apexcybernet

<span class="cmt"># deploy private key — paste the key from chat between the EOF markers</span>
tee /etc/apexcybernet/deploy_key &gt;/dev/null &lt;&lt;'EOF'
<span class="tok">&lt;PASTE DEPLOY KEY FROM CHAT&gt;</span>
EOF
chmod 600 /etc/apexcybernet/deploy_key

<span class="cmt"># webhook secret — paste the secret from chat (keep the quotes)</span>
printf '%s\n' '<span class="tok">&lt;PASTE WEBHOOK SECRET FROM CHAT&gt;</span>' &gt; /etc/apexcybernet/deploy.secret
chmod 640 /etc/apexcybernet/deploy.secret

<span class="cmt"># GitHub host key (so non-interactive git fetch trusts github.com)</span>
ssh-keyscan -t ed25519,rsa github.com 2&gt;/dev/null &gt; /etc/apexcybernet/known_hosts</pre>
    </div>
  </div>

  <!-- STEP 2 -->
  <div class="step">
    <h2><span class="num">2</span> Clone the repo <span class="where vps">on VPS</span></h2>
    <p>Verifies the key, clones to the web root, and persists the SSH command so the
       webhook (running as <code class="inl">www-data</code>) can fetch later.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre>export GIT_SSH_COMMAND="ssh -i /etc/apexcybernet/deploy_key -o IdentitiesOnly=yes -o UserKnownHostsFile=/etc/apexcybernet/known_hosts -o StrictHostKeyChecking=yes"

<span class="cmt"># sanity check — should print: deploy key OK</span>
git ls-remote git@github.com:argonarsoftware-oss/apexcybernet.com.git -h &gt;/dev/null &amp;&amp; echo "deploy key OK"

git clone git@github.com:argonarsoftware-oss/apexcybernet.com.git /var/www/apexcybernet
git -C /var/www/apexcybernet config core.sshCommand "$GIT_SSH_COMMAND"
git -C /var/www/apexcybernet config --add safe.directory /var/www/apexcybernet</pre>
    </div>
    <div class="note warn">If the sanity check does <span class="danger">not</span> print
      <b>deploy key OK</b>, the key in step 1 is wrong — fix it before continuing.</div>
  </div>

  <!-- STEP 3 -->
  <div class="step">
    <h2><span class="num">3</span> PHP extensions & Composer deps <span class="where vps">on VPS</span></h2>
    <p>Installs what the composer packages need (PhpSpreadsheet / PayRex), then the vendor dir.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre>apt-get update -qq
apt-get install -y composer php-zip php-gd php-mbstring php-xml php-curl php-mysql
cd /var/www/apexcybernet &amp;&amp; composer install --no-dev --no-interaction --optimize-autoloader</pre>
    </div>
  </div>

  <!-- STEP 4 -->
  <div class="step">
    <h2><span class="num">4</span> Database <span class="where vps">on VPS</span></h2>
    <p>Creates the <code class="inl">apexcybernet</code> database. First confirm the old site's DB name,
       then clone its schema + data so the new site works immediately.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre><span class="cmt"># confirm the existing site's DB name (expected: argonar_construction)</span>
grep dbname /var/www/argonar/includes/db.php

mysql -u root -e "CREATE DATABASE IF NOT EXISTS apexcybernet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

<span class="cmt"># clone schema + DATA from the old DB into apexcybernet</span>
mysqldump -u root --single-transaction --routines argonar_construction | mysql -u root apexcybernet</pre>
    </div>
    <div class="note">Want a <b>clean start</b> (correct tables, no copied accounts/balances) instead?
      Use <code class="inl">--no-data</code>:
      <code class="inl">mysqldump -u root --no-data --routines argonar_construction | mysql -u root apexcybernet</code></div>
    <div class="note warn">If <code class="inl">grep</code> shows a different DB name, substitute it in the
      <code class="inl">mysqldump</code> command. If MySQL root needs a password, add <code class="inl">-p</code>.</div>
  </div>

  <!-- STEP 5 -->
  <div class="step">
    <h2><span class="num">5</span> Permissions <span class="where vps">on VPS</span></h2>
    <p>Web server owns the tree; <code class="inl">www-data</code> must read the key/secret for webhook fetches.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre>mkdir -p /var/www/apexcybernet/logs /var/www/apexcybernet/uploads
chown -R www-data:www-data /var/www/apexcybernet
chown www-data:www-data /etc/apexcybernet/deploy_key /etc/apexcybernet/deploy.secret /etc/apexcybernet/known_hosts
chmod 600 /etc/apexcybernet/deploy_key</pre>
    </div>
  </div>

  <!-- STEP 6 -->
  <div class="step">
    <h2><span class="num">6</span> Apache vhost + SSL <span class="where vps">on VPS</span></h2>
    <p>Creates the site, enables it, then issues a Let's Encrypt cert. DNS already points here.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre>cat &gt; /etc/apache2/sites-available/apexcybernet.conf &lt;&lt;'EOF'
&lt;VirtualHost *:80&gt;
    ServerName apexcybernet.com
    ServerAlias www.apexcybernet.com
    DocumentRoot /var/www/apexcybernet
    &lt;Directory /var/www/apexcybernet&gt;
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    <span class="cmt"># never serve the git metadata</span>
    &lt;DirectoryMatch "/var/www/apexcybernet/\.git"&gt;
        Require all denied
    &lt;/DirectoryMatch&gt;
    ErrorLog ${APACHE_LOG_DIR}/apexcybernet-error.log
    CustomLog ${APACHE_LOG_DIR}/apexcybernet-access.log combined
&lt;/VirtualHost&gt;
EOF

a2enmod rewrite
a2ensite apexcybernet.conf
apache2ctl configtest &amp;&amp; systemctl reload apache2

<span class="cmt"># issue SSL (answer the prompts; pick redirect HTTP-&gt;HTTPS)</span>
certbot --apache -d apexcybernet.com -d www.apexcybernet.com</pre>
    </div>
    <div class="note warn">If <code class="inl">www.apexcybernet.com</code> isn't in DNS yet, drop the
      <code class="inl">-d www.apexcybernet.com</code> or certbot will fail validation.</div>
  </div>

  <!-- STEP 7 -->
  <div class="step">
    <h2><span class="num">7</span> Test & clean up <span class="where vps">on VPS</span></h2>
    <p>Confirm it serves over HTTPS, then remove this guide from the live site.</p>
    <div class="cmd">
      <button class="copy">copy</button>
<pre>curl -I https://apexcybernet.com/

<span class="cmt"># remove this internal guide from the live web root</span>
rm -f /var/www/apexcybernet/deploy-guide.php</pre>
    </div>
    <div class="note">From now on: push any commit whose message contains
      <code class="inl">[deploy]</code> to <b>main</b> → GitHub webhook hits
      <code class="inl">/webhook-deploy.php</code> → this box runs
      <code class="inl">git fetch + reset --hard origin/main</code>. Watch it with
      <code class="inl">tail -f /var/www/apexcybernet/logs/deploy.log</code>.</div>
  </div>

  <footer>
    Apex Cybernet · internal deploy runbook · delete after setup
  </footer>
</div>

<script>
document.querySelectorAll('.copy').forEach(function(btn){
  btn.addEventListener('click', function(){
    var pre = btn.parentElement.querySelector('pre');
    var text = pre.innerText.replace(/ /g,' ');
    navigator.clipboard.writeText(text).then(function(){
      var old = btn.textContent; btn.textContent = 'copied'; btn.classList.add('done');
      setTimeout(function(){ btn.textContent = old; btn.classList.remove('done'); }, 1400);
    });
  });
});
</script>
</body>
</html>
