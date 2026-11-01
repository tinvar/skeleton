<?php

declare(strict_types=1);

namespace App\Http\Pages;

use App\Http\Data\HomePageData;
use App\Kernel;
use App\Database\Models\Note;
use Tinvar\Attributes\Authorize;
use Tinvar\Attributes\Page;

/**
 * The home page: the notes, and a form to leave one.
 *
 * A page is a class with a #[Page] attribute and a loader. Its
 * #[Authorize] asks NotePolicy::viewAny() before the loader runs, and the
 * loader returns a Data object, which is all the view
 * (resources/pages/app/home.tsx) receives. The notes themselves are not
 * loaded here: the view's <NoteList> awaits the RecentNotes query, and
 * Suspense streams the list in when it resolves.
 */
#[Page('/', view: 'app/home', name: 'home'), Authorize('viewAny', Note::class)]
final class Home
{
    public function __invoke(): HomePageData
    {
        return new HomePageData(appName: Kernel::NAME, limit: 20);
    }
}
