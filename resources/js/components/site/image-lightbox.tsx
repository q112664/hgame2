import type { CSSProperties } from 'react';
import Lightbox from 'yet-another-react-lightbox';
import Thumbnails from 'yet-another-react-lightbox/plugins/thumbnails';
import 'yet-another-react-lightbox/styles.css';
import 'yet-another-react-lightbox/plugins/thumbnails.css';

export type LightboxSlide = {
    src: string;
    alt?: string;
};

type Props = {
    slides: LightboxSlide[];
    index: number;
    onClose: () => void;
    onIndexChange?: (index: number) => void;
};

export function ImageLightbox({
    slides,
    index,
    onClose,
    onIndexChange,
}: Props) {
    return (
        <Lightbox
            open={index >= 0}
            close={onClose}
            index={Math.max(index, 0)}
            slides={slides}
            plugins={[Thumbnails]}
            thumbnails={{
                position: 'bottom',
                border: 0,
                borderRadius: 6,
                padding: 0,
                gap: 8,
                imageFit: 'cover',
                vignette: false,
            }}
            controller={{ closeOnBackdropClick: true }}
            on={{
                view: ({ index: nextIndex }) => onIndexChange?.(nextIndex),
            }}
            styles={{
                root: {
                    '--yarl__thumbnails_container_background_color':
                        'rgba(0, 0, 0, 0.88)',
                } as CSSProperties,
                container: { backgroundColor: 'rgba(0, 0, 0, 0.88)' },
            }}
        />
    );
}
