import { HTMLAttributes } from 'react';
import { clsx } from 'clsx';

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: 'success' | 'danger' | 'warning' | 'info' | 'gray';
}

export function Badge({ className, variant = 'gray', children, ...props }: BadgeProps) {
  return (
    <span
      className={clsx(
        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
        {
          'bg-success-100 text-success-800': variant === 'success',
          'bg-danger-100 text-danger-800': variant === 'danger',
          'bg-warning-100 text-warning-800': variant === 'warning',
          'bg-primary-100 text-primary-800': variant === 'info',
          'bg-gray-100 text-gray-800': variant === 'gray',
        },
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
}