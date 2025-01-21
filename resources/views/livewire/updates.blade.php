<div class="container">
  <h1>Updates</h1>

  <h2 id="2025-01-21">2025-01-21</h2>
  <ul>
    <li>Quietly started logging connection exceptions, with the eventual goal of failing feeds and sending notifications when Feed Canary fails to connect within 24 hours.</li>
  </ul>

  <h2 id="2024-09-29">2024-09-29</h2>
  <ul>
    <li>Overhauled front end to use Livewire and Blade.</li>
    <li>Added the ability to manually re-check a confirmed feed.</li>
    <li>Made feed management pages feel more alive.</li>
    <li>Fixed 500 error when trying to add a URL Feed Canary can’t connect to.</li>
    <li>Feed delete button now includes a confirm step.</li>
  </ul>

  <h2 id="2024-08-22">2024-08-22</h2>
  <ul>
    <li>Refactored quite a bit of code and added tests.</li>
    <li>Started automating PHPStan, Pint, and Pest checks with GitHub Actions.</li>
  </ul>

  <h2 id="2024-08-20">2024-08-20</h2>
  <ul>
    <li>Styled HTML messages and added plain-text alternatives.</li>
  </ul>

  <h2 id="2024-08-08">2024-08-08</h2>
  <ul>
    <li>Switched to <a href="https://resend.com">Resend</a> for the primary mailer.</li>
  </ul>

  <h2 id="2024-07-23">2024-07-23</h2>
  <ul>
    <li>Started comparing content hashes to avoid repeatedly checking the validity of the same content.</li>
  </ul>

  <h2 id="2024-05-27">2024-05-27</h2>
  <ul>
    <li>Inspired by a <a href="https://www.mailgun.com">Mailgun</a> outage, added <a href="https://mailtrap.io">Mailtrap</a> as a failover sender.</li>
  </ul>

  <h2 id="2023-12-27">2023-12-27</h2>
  <ul>
    <li>Added a simple <a href="/status">status page</a>.</li>
    <li>Expanded validation to use <a href="https://github.com/laminas/laminas-feed">laminas-feed</a>, and fall back to <a href="https://www.feedvalidator.org">feedvalidator.org</a> if the <a href="https://validator.w3.org/feed/">W3C validator</a> is offline.</li>
  </ul>

  <h2 id="2023-12-23">2023-12-23</h2>
  <ul>
    <li>Added support for JSON feeds.</li>
    <li>Added dark mode support.</li>
    <li>Improved signup form validation.</li>
  </ul>
</div>
