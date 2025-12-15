@extends('mail.layouts.default')

@section('content')
  <p>Your check for <strong>{{ $feed->url }}</strong> has been manually removed.</p>

  <p><strong>Reason:</strong> {{ $reason }}</p>

  <p>Please get in touch if this seems like a mistake, and feel free to re-add your feed!</p>
@endsection
