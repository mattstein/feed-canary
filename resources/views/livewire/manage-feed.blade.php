@section('robots', 'noindex')
<div class="container">
  <h1 class="feed-url" wire:poll.10s="refreshCheckAvailability">
    <a href="{{ $feed->url }}">
      {{ $feed->url }}
    </a>
    <button
      wire:click.prevent="check"
      wire:loading.attr="disabled"
      wire:loading.class.add="opacity-50"
      x-bind:disabled="$wire.canCheck == false"
      x-bind:title="$wire.canCheck ? '' : 'You can only re-check once every thirty seconds.'"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
      </svg>
      <span class="sr-only">Re-Check Now</span>
    </button>
  </h1>

  <div>
    @if (! $feed->confirmed)
      <p>Check your email for a link to activate this monitor, or push the big button to delete it.</p>
    @endif

    @if ($feed->hasFailingConnection())
      <p>🤔️ Feed connection failed, which may only be a temporary clog in the tubes:</p>
      <blockquote>{{ $feed->latestConnectionFailure()->message }}</blockquote>
      <p>This doesn’t affect the check status, but if we can’t connect again within {{ config('app.connection_failure_threshold') / 60 / 60 }} hours it’ll be considered a failure and you’ll receive a notification.</p>
    @else
      @if ($feed->confirmed && $feed->status === 'healthy')
        <p wire:loading.remove wire:target="check">✅ Looks great!
          @if ($feed->latestCheck() && $feed->latestCheck()->is_valid) <code>{{ $feed->latestCheck()->status }}</code> response with valid markup.@endif
        </p>
      @elseif ($feed->confirmed && $feed->status === 'failing')
        <div wire:loading.remove wire:target="check">
          <p>⛔️ Broken feed! Status code was <code>{{ $feed->latestCheck()->status }}</code>{{ $feed->latestCheck()->is_valid ? '.' : ' and it’s invalid.' }}</p>
          <p><a href="{{ $feed->validatorUrl() }}" target="_blank">Troubleshoot →</a></p>
        </div>
      @endif
    @endif

    <p wire:loading wire:target="check"><i>Checking...</i></p>
  </div>

  <hr/>

  <div>
    @if ($feed->latestCheck())
      <p>Last checked <relative-time update="1"><time datetime="{{ $feed->latestCheck()->updated_at->format(DATE_ATOM) }}">{{ $feed->latestCheck()->updated_at }}</time></relative-time>.</p>
    @endif
    @if ($feed->previousCheck())
      <p>Previously {{ $feed->previousCheck()->is_valid ? 'valid' : 'invalid' }} when checked <relative-time update="1"><time datetime="{{ $feed->previousCheck()->updated_at->format(DATE_ATOM) }}">{{ $feed->latestCheck()->updated_at }}</time></relative-time>.</p>
    @endif
  </div>

  <button class="danger"
    wire:click.prevent="delete"
    wire:confirm="And you definitely want to delete this, right?"
  >
    Delete
  </button>
</div>
