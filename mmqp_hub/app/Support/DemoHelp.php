<?php

namespace App\Support;

use Illuminate\Http\Request;

class DemoHelp
{
    public function forRequest(Request $request): ?array
    {
        if (! config('demo.enabled')) {
            return null;
        }

        foreach (config('demo-help', []) as $topic => $help) {
            if (! $request->routeIs(...($help['routes'] ?? []))) {
                continue;
            }

            foreach ($help['query'] ?? [] as $key => $value) {
                if ($request->query($key) !== $value) {
                    continue 2;
                }
            }

            $user = $request->user();
            if ($user && ! $user->isEmployee() && isset($help['administrator'])) {
                $help = array_replace($help, $help['administrator']);
            }

            return ['topic' => $topic] + $help;
        }

        return null;
    }
}
