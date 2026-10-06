import { Head, Link } from '@inertiajs/react';
import { SitePageContainer } from '@/components/site/site-page-container';
import { Button } from '@/components/ui/button';
import { SiteLayout } from '@/layouts/site-layout';
import { home } from '@/routes';
import { index as resourcesIndex } from '@/routes/resources';

export default function NotFound() {
    return (
        <SiteLayout>
            <Head title="Page not found">
                <meta head-key="robots" name="robots" content="noindex" />
            </Head>

            <SitePageContainer className="min-h-[70vh] items-center justify-center">
                <div className="flex w-full max-w-md flex-col items-center gap-3 text-center">
                    <h1 className="font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                        Page not found
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        This page is not in the catalog.
                    </p>
                    <div className="mt-2 flex flex-wrap justify-center gap-2">
                        <Button asChild>
                            <Link href={home()} prefetch>
                                Home
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={resourcesIndex()} prefetch>
                                Games
                            </Link>
                        </Button>
                    </div>
                </div>
            </SitePageContainer>
        </SiteLayout>
    );
}
