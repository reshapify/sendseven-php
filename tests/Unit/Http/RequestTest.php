<?php

declare(strict_types=1);

use Reshapify\SendSeven\Http\Request;

it('builds a query string without nulls, with repeated lists and readable booleans', function (): void {
    $request = Request::get('/contacts', ['page' => 2, 'search' => null, 'tag_id' => ['a', 'b'], 'include_live_chat' => false, 'q' => 'x y']);

    expect($request->queryString())->toBe('page=2&tag_id=a&tag_id=b&include_live_chat=false&q=x%20y');
});

it('finds headers in any letter case', function (): void {
    expect(Request::get('/x')->withHeader('X-Tenant-ID', 't1')->header('x-tenant-id'))->toBe('t1');
});
