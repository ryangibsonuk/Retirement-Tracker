import Link from "next/link";

export default function HomePage() {
  return (
    <div className="min-h-screen flex flex-col">
      <header className="border-b border-[var(--calc-border)] bg-white/80 backdrop-blur">
        <div className="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
          <span className="font-semibold text-lg text-[var(--calc-text)]">
            Retirement Tracker
          </span>
          <nav className="flex gap-4">
            <Link
              href="/login"
              className="text-[var(--calc-muted)] hover:text-[var(--calc-primary)]"
            >
              Log in
            </Link>
            <Link
              href="/signup"
              className="rounded-xl bg-[var(--calc-primary)] px-4 py-2 text-white font-medium hover:bg-[var(--calc-primary-hover)]"
            >
              Sign up
            </Link>
          </nav>
        </div>
      </header>

      <main className="flex-1 max-w-4xl mx-auto w-full px-4 py-16">
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-[var(--calc-text)] mb-4">
            Your retirement, one place
          </h1>
          <p className="text-lg text-[var(--calc-muted)] max-w-xl mx-auto">
            Enter your numbers once. Update when things change. We’ll nudge you
            monthly so you stay on top of where you’re at — at any retirement
            age.
          </p>
        </div>

        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <Link
            href="/signup"
            className="rounded-2xl bg-[var(--calc-primary)] px-8 py-4 text-white font-semibold text-center hover:bg-[var(--calc-primary-hover)] transition"
          >
            Get started
          </Link>
          <Link
            href="/login"
            className="rounded-2xl border-2 border-[var(--calc-border)] px-8 py-4 text-[var(--calc-text)] font-semibold text-center hover:bg-[var(--calc-soft)] transition"
          >
            I already have an account
          </Link>
        </div>
      </main>
    </div>
  );
}
