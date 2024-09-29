<div class="container">

  @if (session('message'))
    <div class="form-message">
      {{ session('message') }}
    </div>
  @endif

  <div class="intro">
    <p>Feed Canary watches your RSS feed and emails you if it’s missing or invalid.</p>
  </div>

  @if (! empty($feedErrors))
    <div class="form-error">
      {{ $feedErrors->first() }}
    </div>
  @endif

  <form method="post" wire:submit="create">
    <label for="url">Feed URL <small>for a valid RSS feed</small></label>
    <input type="url" id="url" wire:model="url" required>
    <div class="form-error">@error('url') {{ $message }} @enderror</div>

    <label for="email">Email Address <small>to confirm and activate (not SPAM)</small></label>
    <input type="email" id="email" wire:model="email" required>
    <div class="form-error">@error('email') {{ $message }} @enderror</div>

    <button wire:loading.attr="disabled">
      <span wire:loading>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="waiting-icon">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
        </svg>
        <span class="sr-only">Checking...</span>
      </span>
      <span wire:loading.remove>+ Confirm Feed</span>
    </button>
  </form>

  <x-faq />
</div>
