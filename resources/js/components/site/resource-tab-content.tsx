import { Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Download,
    HardDrive,
    Images,
    Info,
    Languages,
} from 'lucide-react';
import { useState } from 'react';
import { useAuthDialog } from '@/components/auth/auth-dialog';
import type { LightboxSlide } from '@/components/site/image-lightbox';
import { PlatformIcon } from '@/components/site/platform-icon';
import { RelatedResources } from '@/components/site/related-resources';
import {
    downloadButtonClassName,
    fileSizeBadgeClassName,
    languageBadgeClassName,
    platformBadgeClassName,
    releaseFooterClassName,
    releaseFooterInnerClassName,
    tagBadgeClassName,
} from '@/components/site/resource-detail-styles';
import { RichHtml } from '@/components/site/rich-html';
import { SiteEmptyState } from '@/components/site/site-empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import { useImageLoadState } from '@/hooks/use-image-load-state';
import {
    formatDate,
    formatVersion,
    formatViews,
} from '@/lib/resource-formatters';
import type { ResourceTab } from '@/lib/resource-tabs';
import { cn } from '@/lib/utils';
import { show as downloadLinkShow } from '@/routes/download-links';
import { tag as resourcesTag } from '@/routes/resources';
import { show as userShow } from '@/routes/users';
import type { GameCard, GameDetail } from '@/types/resources';

/** Collapsed height ≈ three prose-sm lines before “Show more”. */
const RELEASE_DESCRIPTION_COLLAPSED_MAX_PX = 72;

/**
 * Downloads for one package. The game-wide total lives on the catalog card and
 * the rankings; on a package bar the reader wants that package's own number.
 */
function DownloadCount({
    count,
    className,
}: {
    count: number;
    className?: string;
}) {
    const formatted = formatViews(count);

    return (
        <span
            className={cn(
                'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md px-2.5',
                // On the card surface, so the chip uses the same muted fill as
                // every other quiet chip in a card.
                'bg-muted text-sm font-medium text-muted-foreground tabular-nums',
                'dark:bg-white/10',
                className,
            )}
            title={`${formatted} downloads`}
        >
            <Download className="size-3.5 shrink-0" aria-hidden />
            <span className="sr-only">Downloads </span>
            {formatted}
        </span>
    );
}

const releaseDescriptionClassName = cn(
    'prose-sm max-w-none text-muted-foreground',
    '[--tw-prose-body:var(--color-muted-foreground)]',
    'prose-p:my-1.5 prose-p:leading-relaxed',
    'first:prose-p:mt-0 last:prose-p:mb-0',
    '[&:has(>p:only-child:empty)]:hidden',
);

type CollapsibleReleaseDescriptionProps = {
    html: string;
};

/**
 * Decide collapse from the HTML itself so SSR and the first client paint match.
 * Measuring after paint unclamps long notes (scrollHeight ≈ 72px while clipped)
 * and then reclamps — that is the refresh flash.
 */
function releaseDescriptionNeedsToggle(html: string): boolean {
    if (/<img\b/i.test(html)) {
        return true;
    }

    const withBreaks = html
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/(p|div|li|h[1-6]|tr|blockquote)>/gi, '\n');

    const text = withBreaks
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&#(\d+);/g, (_, code: string) =>
            String.fromCharCode(Number(code)),
        )
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n[ \t]+/g, '\n')
        .replace(/[ \t]{2,}/g, ' ')
        .trim();

    if (text === '') {
        return false;
    }

    const lineCount = text
        .split('\n')
        .filter((line) => line.trim() !== '').length;

    return lineCount > 3 || text.length > 140;
}

function CollapsibleReleaseDescription({
    html,
}: CollapsibleReleaseDescriptionProps) {
    const [expanded, setExpanded] = useState(false);
    const needsToggle = releaseDescriptionNeedsToggle(html);
    const isClamped = needsToggle && !expanded;

    return (
        <div className="flex flex-col">
            <div className="relative">
                <div
                    style={
                        isClamped
                            ? {
                                  maxHeight:
                                      RELEASE_DESCRIPTION_COLLAPSED_MAX_PX,
                                  overflow: 'hidden',
                              }
                            : undefined
                    }
                >
                    <RichHtml
                        html={html}
                        className={releaseDescriptionClassName}
                    />
                </div>
                {isClamped ? (
                    <div
                        className="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-card to-transparent"
                        aria-hidden
                    />
                ) : null}
            </div>
            {needsToggle ? (
                <button
                    type="button"
                    className={cn(
                        'mt-1.5 flex w-full cursor-pointer items-center gap-2 text-[11px] tracking-wide text-muted-foreground uppercase',
                        'transition-colors hover:text-foreground',
                        'focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                    )}
                    aria-expanded={expanded}
                    onClick={() => setExpanded((value) => !value)}
                >
                    <span className="h-px min-w-0 flex-1 bg-border" />
                    <span className="inline-flex shrink-0 items-center gap-0.5">
                        {expanded ? 'Less' : 'More'}
                        {expanded ? (
                            <ChevronUp className="size-3 opacity-70" />
                        ) : (
                            <ChevronDown className="size-3 opacity-70" />
                        )}
                    </span>
                    <span className="h-px min-w-0 flex-1 bg-border" />
                </button>
            ) : null}
        </div>
    );
}

type ResourceScreenshotProps = {
    src: string;
    alt: string;
    onOpen: () => void;
};

export function ResourceScreenshot({
    src,
    alt,
    onOpen,
}: ResourceScreenshotProps) {
    const { imageRef, loaded, markLoaded } = useImageLoadState(src);

    return (
        <button
            type="button"
            onClick={onOpen}
            className="relative aspect-video overflow-hidden rounded-md border border-border bg-card transition-[border-color] hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            aria-label={`View ${alt}`}
        >
            {!loaded ? (
                <div className="absolute inset-0 flex items-center justify-center bg-muted/40">
                    <Spinner className="size-5 text-muted-foreground" />
                </div>
            ) : null}
            <img
                ref={imageRef}
                src={src}
                alt={alt}
                className={cn(
                    'size-full object-cover transition-opacity duration-200',
                    loaded ? 'opacity-100' : 'opacity-0',
                )}
                loading="lazy"
                decoding="async"
                referrerPolicy="no-referrer"
                onLoad={markLoaded}
                onError={markLoaded}
            />
        </button>
    );
}

type ResourceDetailVersionsProps = {
    resource: GameDetail;
};

/**
 * Description heading plus its language switcher, and every version's body.
 *
 * The switcher is a dropdown rather than a second tab row: the page already has
 * one tab row above this panel, and a game may carry up to 20 translations
 * (plus the English original), which no horizontal tab strip survives. Inactive
 * versions stay mounted and hidden so crawlers still see every translation.
 */
function ResourceDetailVersions({ resource }: ResourceDetailVersionsProps) {
    const versions =
        resource.detailVersions.length > 0
            ? resource.detailVersions
            : [
                  {
                      code: 'original',
                      name: 'English',
                      html: resource.description,
                      isDefault: true,
                  },
              ];
    const defaultVersion =
        versions.find((version) => version.isDefault) ?? versions[0];
    const [activeCode, setActiveCode] = useState(defaultVersion.code);
    // A stale code (the version list changed under us) falls back to the default.
    const activeVersion =
        versions.find((version) => version.code === activeCode) ??
        defaultVersion;

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                <h2
                    id="resource-description-heading"
                    className="font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                >
                    Description
                </h2>
                {versions.length > 1 ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className={cn(
                                    '-me-1.5 gap-1.5 text-sm',
                                    // Dropdown triggers in this project carry no
                                    // focus ring; the open state shows through the
                                    // ghost variant's aria-expanded background.
                                    'focus-visible:border-transparent focus-visible:ring-0 focus-visible:outline-none',
                                )}
                                aria-label={`Description language: ${activeVersion.name}`}
                            >
                                <Languages
                                    data-icon="inline-start"
                                    className="size-3.5"
                                />
                                <span className="max-w-40 truncate">
                                    {activeVersion.name}
                                </span>
                                <ChevronDown
                                    data-icon="inline-end"
                                    className="size-3.5 opacity-70"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuRadioGroup
                                value={activeVersion.code}
                                onValueChange={setActiveCode}
                            >
                                {versions.map((version) => (
                                    <DropdownMenuRadioItem
                                        key={version.code}
                                        value={version.code}
                                    >
                                        {version.name}
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>
            {versions.map((version) => (
                <div
                    key={version.code}
                    className={cn(
                        version.code !== activeVersion.code && 'hidden',
                    )}
                >
                    <RichHtml html={version.html} />
                </div>
            ))}
        </>
    );
}

type Props = {
    resource: GameDetail;
    activeTab: ResourceTab;
    screenshotSlides: LightboxSlide[];
    onOpenLightbox: (slides: LightboxSlide[], index: number) => void;
    /** Site-wide notice HTML above download packages (empty when disabled). */
    resourceNotice?: string;
    /** When false, the per-package download count is omitted from each package bar. */
    showDownloadCount?: boolean;
    /** When true, guests open the login dialog instead of the download jump page. */
    requireLoginToDownload?: boolean;
    related?: GameCard[];
};

export function ResourceTabContent({
    resource,
    activeTab,
    screenshotSlides,
    onOpenLightbox,
    resourceNotice = '',
    showDownloadCount = true,
    requireLoginToDownload = false,
    related = [],
}: Props) {
    const page = usePage();
    const { openAuthDialog } = useAuthDialog();
    const downloadsRequireLogin =
        requireLoginToDownload && !page.props.auth.user;

    return (
        <div>
            <div
                className="flex flex-col gap-4 sm:gap-5"
                hidden={activeTab !== 'details'}
            >
                <div className="flex flex-col gap-5 sm:gap-6">
                    <section
                        className="flex flex-col gap-3 sm:gap-4"
                        aria-labelledby="resource-description-heading"
                    >
                        <ResourceDetailVersions resource={resource} />
                    </section>

                    {resource.tags.length > 0 ? (
                        <section
                            className="flex flex-col gap-3 sm:gap-4"
                            aria-labelledby="resource-tags-heading"
                        >
                            <h2
                                id="resource-tags-heading"
                                className="font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                            >
                                Tags
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                {resource.tags.map((tag) => (
                                    <Link
                                        key={tag.slug}
                                        href={resourcesTag.url(tag.slug)}
                                        className={tagBadgeClassName}
                                        prefetch
                                    >
                                        {tag.name}
                                    </Link>
                                ))}
                            </div>
                        </section>
                    ) : null}
                </div>

                <RelatedResources resources={related} title="Related games" />
            </div>

            <div
                className="flex flex-col gap-3 sm:gap-4"
                hidden={activeTab !== 'downloads'}
                aria-labelledby="resource-downloads-heading"
            >
                <h2
                    id="resource-downloads-heading"
                    className="font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                >
                    Downloads
                </h2>
                {resourceNotice !== '' ? (
                    <div
                        role="note"
                        className={cn(
                            'flex gap-2.5 rounded-md border border-info/20 bg-info/8 px-3 py-2.5 sm:px-4',
                            'dark:border-info/25 dark:bg-info/12',
                        )}
                    >
                        <Info
                            className="mt-0.5 size-4 shrink-0 text-info"
                            aria-hidden
                        />
                        <RichHtml
                            html={resourceNotice}
                            className={cn(
                                'min-w-0 flex-1 text-sm leading-relaxed',
                                'prose-p:my-1 prose-p:first:mt-0 prose-p:last:mb-0',
                                'prose-ol:my-1 prose-ul:my-1 prose-li:my-0',
                                'prose-headings:my-1.5 prose-headings:text-sm',
                                'prose-a:font-medium',
                            )}
                        />
                    </div>
                ) : null}
                {resource.releases.length > 0 ? (
                    resource.releases.map((release) => {
                        const hasLinks = release.downloadLinks.length > 0;
                        const multiLinks = release.downloadLinks.length > 1;
                        const releaseUpdatedAt = release.downloadsUpdatedAt;
                        const showReleaseUpdate =
                            releaseUpdatedAt !== null &&
                            releaseUpdatedAt !== '';
                        /**
                         * One date per bar: the update when the package carries
                         * one, otherwise when it was listed. The list date only
                         * ever reads as the package's, never the game's — the hero
                         * states the game's date separately.
                         */
                        const packageUpdated =
                            showReleaseUpdate && releaseUpdatedAt ? (
                                <span
                                    className="inline-flex min-w-0 items-center gap-1 text-xs leading-4 text-muted-foreground"
                                    title="Package downloads updated"
                                >
                                    {/* Keeps the accent the old chip used, so
                                        returning readers still spot a refreshed
                                        package. */}
                                    <span className="shrink-0 font-medium text-info">
                                        Updated
                                    </span>
                                    <time
                                        dateTime={releaseUpdatedAt}
                                        className="min-w-0 truncate tabular-nums"
                                    >
                                        {formatDate(releaseUpdatedAt)}
                                    </time>
                                </span>
                            ) : null;

                        const packageListed = release.publishedAt ? (
                            <span
                                className="inline-flex min-w-0 items-center gap-1 text-xs leading-4 text-muted-foreground"
                                title="Package listed"
                            >
                                <span className="shrink-0">Listed</span>
                                <time
                                    dateTime={release.publishedAt}
                                    className="min-w-0 truncate tabular-nums"
                                >
                                    {formatDate(release.publishedAt)}
                                </time>
                            </span>
                        ) : null;

                        const packageDate = packageUpdated ?? packageListed;

                        return (
                            <article
                                key={release.id}
                                className="overflow-hidden rounded-lg border border-border bg-card"
                            >
                                {/* Header: title + version + description */}
                                <div className="flex flex-col gap-2.5 px-4 py-4 sm:px-5">
                                    <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                        <h3 className="min-w-0 font-heading text-base font-semibold tracking-tight text-foreground">
                                            {release.title ??
                                                'Download package'}
                                        </h3>
                                        {release.version ? (
                                            <span className="inline-flex h-6 shrink-0 items-center rounded-md bg-muted px-2 text-xs font-medium text-muted-foreground">
                                                {formatVersion(release.version)}
                                            </span>
                                        ) : null}
                                    </div>

                                    {/* Platform / language / size badges below the title */}
                                    {release.platforms.length > 0 ||
                                    release.languages.length > 0 ||
                                    release.fileSize ? (
                                        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                            {release.platforms.map(
                                                (platform) => (
                                                    <Badge
                                                        key={platform.slug}
                                                        variant="outline"
                                                        className={platformBadgeClassName(
                                                            platform.slug,
                                                        )}
                                                    >
                                                        <PlatformIcon
                                                            slug={platform.slug}
                                                            data-icon="inline-start"
                                                        />
                                                        {platform.name}
                                                    </Badge>
                                                ),
                                            )}
                                            {release.languages.map(
                                                (language) => (
                                                    <Badge
                                                        key={language.code}
                                                        variant="outline"
                                                        className={
                                                            languageBadgeClassName
                                                        }
                                                    >
                                                        {language.name}
                                                    </Badge>
                                                ),
                                            )}
                                            {release.fileSize ? (
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        fileSizeBadgeClassName
                                                    }
                                                >
                                                    <HardDrive data-icon="inline-start" />
                                                    {release.fileSize}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    ) : null}

                                    {release.description ? (
                                        <CollapsibleReleaseDescription
                                            html={release.description}
                                        />
                                    ) : null}
                                </div>

                                {/* Footer: meta chips (left) + download CTAs (right) */}
                                <div className={releaseFooterClassName}>
                                    <div
                                        className={releaseFooterInnerClassName}
                                    >
                                        {/*
                                              Mobile: author and package dates on the
                                              left, download count on the right.
                                              sm+: count and download CTAs on the right.
                                            */}
                                        <div className="flex w-full min-w-0 items-center justify-between gap-2 sm:w-auto sm:justify-start">
                                            {release.contributor ? (
                                                <div className="flex min-w-0 flex-1 items-center gap-2 sm:max-w-[min(100%,20rem)] sm:flex-none">
                                                    {/* Only the avatar links out:
                                                        the name and the dates
                                                        are labels, and a click
                                                        on them should not
                                                        navigate away. */}
                                                    <Link
                                                        href={userShow(
                                                            release.contributor
                                                                .slug,
                                                        )}
                                                        prefetch
                                                        className={cn(
                                                            'shrink-0 rounded-full',
                                                            'transition-opacity hover:opacity-85',
                                                            'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                                                        )}
                                                        title={`Contributed by ${release.contributor.name}`}
                                                    >
                                                        <UserAvatar
                                                            user={
                                                                release.contributor
                                                            }
                                                            className="size-8"
                                                            fallbackClassName="bg-primary/15 text-xs text-primary"
                                                        />
                                                    </Link>
                                                    <span className="flex min-w-0 flex-col justify-center gap-0.5 py-px">
                                                        <span className="truncate text-sm leading-4 font-medium text-foreground">
                                                            {
                                                                release
                                                                    .contributor
                                                                    .name
                                                            }
                                                        </span>
                                                        {packageDate}
                                                    </span>
                                                </div>
                                            ) : packageDate ? (
                                                <div className="flex min-w-0 flex-1 items-center sm:flex-none">
                                                    {packageDate}
                                                </div>
                                            ) : null}
                                            <div className="flex shrink-0 items-center gap-2 sm:hidden">
                                                {showDownloadCount ? (
                                                    <DownloadCount
                                                        count={
                                                            release.downloads
                                                        }
                                                    />
                                                ) : null}
                                            </div>
                                        </div>

                                        <div
                                            className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end sm:gap-2"
                                            role="group"
                                            aria-label="Package actions"
                                        >
                                            {showDownloadCount ? (
                                                <DownloadCount
                                                    count={release.downloads}
                                                    className="hidden sm:inline-flex"
                                                />
                                            ) : null}
                                            {hasLinks
                                                ? release.downloadLinks.map(
                                                      (link, index) => {
                                                          const name =
                                                              link.label ||
                                                              'Download';
                                                          const ariaLabel =
                                                              name ===
                                                              'Download'
                                                                  ? multiLinks
                                                                      ? `Download ${index + 1}`
                                                                      : 'Download'
                                                                  : `Download from ${name}`;

                                                          const buttonClassName =
                                                              cn(
                                                                  downloadButtonClassName,
                                                                  'w-full sm:w-auto',
                                                              );

                                                          if (
                                                              downloadsRequireLogin
                                                          ) {
                                                              return (
                                                                  <Button
                                                                      key={
                                                                          link.id
                                                                      }
                                                                      type="button"
                                                                      variant="default"
                                                                      size="sm"
                                                                      className={
                                                                          buttonClassName
                                                                      }
                                                                      aria-label={
                                                                          ariaLabel
                                                                      }
                                                                      onClick={() =>
                                                                          openAuthDialog(
                                                                              'login',
                                                                              {
                                                                                  redirect:
                                                                                      downloadLinkShow.url(
                                                                                          link.id,
                                                                                      ),
                                                                              },
                                                                          )
                                                                      }
                                                                  >
                                                                      <Download data-icon="inline-start" />
                                                                      <span className="truncate">
                                                                          {name}
                                                                      </span>
                                                                  </Button>
                                                              );
                                                          }

                                                          return (
                                                              <Button
                                                                  key={link.id}
                                                                  asChild
                                                                  variant="default"
                                                                  size="sm"
                                                                  className={
                                                                      buttonClassName
                                                                  }
                                                              >
                                                                  <Link
                                                                      href={downloadLinkShow(
                                                                          link.id,
                                                                      )}
                                                                      target="_blank"
                                                                      rel="noopener noreferrer"
                                                                      aria-label={
                                                                          ariaLabel
                                                                      }
                                                                  >
                                                                      <Download data-icon="inline-start" />
                                                                      <span className="truncate">
                                                                          {name}
                                                                      </span>
                                                                  </Link>
                                                              </Button>
                                                          );
                                                      },
                                                  )
                                                : null}
                                        </div>
                                    </div>
                                </div>
                            </article>
                        );
                    })
                ) : (
                    <SiteEmptyState
                        title="No downloads available yet"
                        description="Check back later for release packages and download links."
                    />
                )}
            </div>

            <div
                className="flex flex-col gap-3 sm:gap-4"
                hidden={activeTab !== 'screenshots'}
                aria-labelledby="resource-screenshots-heading"
            >
                <h2
                    id="resource-screenshots-heading"
                    className="font-heading text-base font-semibold tracking-tight text-foreground sm:text-lg"
                >
                    Screenshots
                </h2>
                {resource.screenshots.length > 0 ? (
                    <div className="grid grid-cols-1 content-start gap-3 sm:grid-cols-3">
                        {resource.screenshots.map((screenshot, index) => (
                            <ResourceScreenshot
                                key={screenshot}
                                src={screenshot}
                                alt={`${resource.title} screenshot ${index + 1}`}
                                onOpen={() =>
                                    onOpenLightbox(screenshotSlides, index)
                                }
                            />
                        ))}
                    </div>
                ) : (
                    <SiteEmptyState
                        icon={Images}
                        title="No images yet"
                        description="This resource does not have screenshots uploaded."
                    />
                )}
            </div>
        </div>
    );
}
