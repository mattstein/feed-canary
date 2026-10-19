<x-layout :title="'About'">
  <div class="container">
    <h1>About Feed Canary</h1>

    <p>Feed Canary watches your RSS and JSON feeds and tells you when something breaks.</p>

    <h2>How It Works</h2>

    <ol class="steps">
      <li>
        <p><strong>Add a feed.</strong> Paste any RSS or JSON feed URL with the email address where you want notifications.</p>
      </li>
      <li>
        <p><strong>Confirm ownership.</strong> Feed Canary sends a one-time confirmation link to prove the email belongs to you.</p>
      </li>
      <li>
        <p><strong>Relax.</strong> Your feed is checked every ten minutes. If it goes missing, stops validating, or the connection fails for a full day, you get an email.</p>      </li>
      </li>
      <li>
        <p><strong>Fix and confirm.</strong> Once your feed is healthy again, Feed Canary notices and lets you know the problem is resolved.</p>
      </li>
    </ol>

    <h2>Manage Anytime</h2>

    <p>Every email links back to a management page where you can see recent check history, manually re-check, or delete the monitor.</p>

    <h2>Under the Hood</h2>

    <ul>
      <li>RSS validated with <a href="https://github.com/laminas/laminas-feed">laminas-feed</a>, falling back to the <a href="https://validator.w3.org/feed/">W3C Feed Validator</a> and <a href="https://www.feedvalidator.org">feedvalidator.org</a>.</li>
      <li>JSON feeds validated against the <a href="https://www.jsonfeed.org">JSON Feed spec</a>.</li>
      <li>Content hashes skip redundant checks when nothing changed.</li>
      <li>Connection failures are logged, and feeds that stay unreachable for 24 hours trigger a notification.</li>
    </ul>

    <p>Laravel on a modest VPS through Coolify, behind Cloudflare. Emails sent with useSend. Analytics self-hosted with Umami. <a href="https://github.com/mattstein/feed-canary">Source on GitHub</a>.</p>

    <p><a href="/">← Back home</a></p>
  </div>
</x-layout>