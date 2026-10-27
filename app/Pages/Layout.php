<?php

declare(strict_types=1);

namespace App\Pages;

use Tinvar\Attributes\Layout as LayoutAttribute;

/**
 * The shell around every page: the header and the footer
 * (resources/pages/app/layout.tsx). At `/`, so it wraps every route. It
 * has no loader and no model input, so nothing for an #[Authorize] to
 * name: each page under it declares its own.
 */
#[LayoutAttribute('/', view: 'app/layout')]
final class Layout {}
