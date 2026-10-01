<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Testing;

use LogicException;
use Reshapify\SendSeven\Exceptions\SendSevenException;
use Reshapify\SendSeven\Http\Request;

final class UnexpectedRequest extends LogicException implements SendSevenException
{
    /**
     * @param  list<string>  $patterns
     */
    public static function for(Request $request, array $patterns): self
    {
        return new self(sprintf(
            "The SendSeven fake has no answer for %s. Script one: SendSeven::fake(['%s' => [...]])%s.",
            $request->describe(),
            $request->describe(),
            $patterns === [] ? '' : ' (scripted: '.implode(', ', $patterns).')',
        ));
    }
}
