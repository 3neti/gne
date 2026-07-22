<?php

use App\Domain\Compilation\CompilationSubject;
use App\Domain\Compilation\DocumentResolutionException;

it('represents repository-native subject identity and type', function () {
    $subject = new CompilationSubject('RESERVATION-000001', 'PropertyReservation');

    expect($subject->toArray())->toBe(['identifier' => 'RESERVATION-000001', 'type' => 'PropertyReservation'])
        ->and($subject->equals(new CompilationSubject('RESERVATION-000001', 'PropertyReservation')))->toBeTrue()
        ->and($subject->equals(new CompilationSubject('RESERVATION-000002', 'PropertyReservation')))->toBeFalse();
});

it('rejects malformed subject identity', function (string $identifier, string $type) {
    new CompilationSubject($identifier, $type);
})->with([['', 'PropertyReservation'], ['BAD SUBJECT', 'PropertyReservation'], ['RESERVATION-000001', '']])->throws(DocumentResolutionException::class);
