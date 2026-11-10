import { Link } from '@inertiajs/react';

type Props = { message: string; backHref: string; backLabel: string };

/** builder が null を返したときに出す「見つかりません」 */
export default function NotFound({ message, backHref, backLabel }: Props) {
    return (
        <div className="border-line rounded-lg border bg-white p-6 text-center">
            <p className="mb-4">{message}</p>
            <Link href={backHref} className="text-brand underline">
                {backLabel}
            </Link>
        </div>
    );
}
