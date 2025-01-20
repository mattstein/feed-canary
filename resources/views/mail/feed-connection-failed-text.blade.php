It’s been more than {{ config('app.connection_failure_threshold') / 60 / 60 }} hours since we could last connect to your feed at {{ $feed->url }}.

If this turns out to be an issue on Feed Canary’s end, you’ll get a notification whenever we’re able to reconnect. Otherwise you may want to sure your feed exists!

You can delete these notifications here if you don’t want them anymore: {{ $feed->manageUrl() }}
