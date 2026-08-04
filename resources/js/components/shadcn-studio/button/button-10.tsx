import { TrashIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Gradient sweep style (destructive) from shadcn-studio button-10. */
export const gradientSweepButtonClassName =
    'from-destructive via-destructive/60 to-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 border-0 bg-transparent bg-linear-to-r bg-size-[200%_auto] text-white transition-[background-position] duration-500 hover:bg-transparent hover:bg-position-[99%_center]';

const ButtonDeleteDemo = () => {
    return (
        <Button className={gradientSweepButtonClassName}>
            <TrashIcon />
            Delete
        </Button>
    );
};

export default ButtonDeleteDemo;
