import { Button } from '@/components/ui/button'

const ButtonHeartbeatEffectDemo = () => {
    return (
        <Button
            variant="destructive"
            className="animate-heartbeat bg-destructive! dark:bg-destructive! m-9 text-white"
        >
            Heartbeat Effect
        </Button>
    )
}

export default ButtonHeartbeatEffectDemo
