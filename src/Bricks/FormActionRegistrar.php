<?php

declare(strict_types=1);

namespace BricksGhlConnector\Bricks;

final class FormActionRegistrar
{
    private FormControls $controls;
    private FormSubmitHandler $handler;

    public function __construct(FormControls $controls, FormSubmitHandler $handler)
    {
        $this->controls = $controls;
        $this->handler = $handler;
    }

    public function register(): void
    {
        add_filter('bricks/elements/form/control_groups', [$this->controls, 'addControlGroup']);
        add_filter('bricks/elements/form/controls', [$this->controls, 'addControls']);
        add_action('bricks/form/action/ghl', [$this->handler, 'handle']);
    }
}
