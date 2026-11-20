<?php

use App\Enums\Templates\TemplateVersionStatus as Status;
use App\Enums\Templates\VariableType;

it('allows only draft to published and published to retired', function (Status $from, Status $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'publish a draft' => [Status::Draft, Status::Published, true],
    'retire a published' => [Status::Published, Status::Retired, true],
    'retire a draft' => [Status::Draft, Status::Retired, false],
    'back to draft' => [Status::Published, Status::Draft, false],
    'revive a retired' => [Status::Retired, Status::Published, false],
    'stay' => [Status::Draft, Status::Draft, false],
]);

it('lets only a draft be edited and labels every status in Indonesian', function () {
    expect(array_map(fn (Status $s): bool => $s->isEditable(), Status::cases()))->toBe([true, false, false])
        ->and(array_map(fn (Status $s): string => $s->label(), Status::cases()))->toBe(['Draf', 'Terbit', 'Versi lama']);
});

it('maps the Node variable types, unknown ones to text', function () {
    expect(VariableType::fromNode('number'))->toBe(VariableType::Number)
        ->and(VariableType::fromNode('date'))->toBe(VariableType::Date)
        ->and(VariableType::fromNode('string'))->toBe(VariableType::Text)
        ->and(VariableType::fromNode(null))->toBe(VariableType::Text);
});
