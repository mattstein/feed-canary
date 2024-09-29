<div class="container">
  <h1>Status</h1>

  @if ($lastCheck)
    <p>Last check was <relative-time update="1"><time datetime="{{ $lastCheck->updated_at->format(DATE_ATOM) }}">{{ $lastCheck->updated_at }}</time></relative-time>.</p>
  @else
    <p>Last check was ... never.</p>
  @endif

  <p wire:init="updateW3cStatus">
    {{ $this->getStatusEmoji($w3cStatus) }} validator.w3.org/feed {{ $this->getStatusDescription($w3cStatus) }}
  </p>

  <p wire:init="updateValidatorDotOrgStatus">
    {{ $this->getStatusEmoji($validatorDotOrgStatus) }} feedvalidator.org {{ $this->getStatusDescription($validatorDotOrgStatus) }}
  </p>
</div>
