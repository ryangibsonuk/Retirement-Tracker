import { createServerClient } from "@supabase/ssr";
import { NextResponse } from "next/server";

type CookieToSet = { name: string; value: string; options?: Record<string, unknown> };

export async function POST(request: Request) {
  const origin = request.headers.get("origin") ?? new URL(request.url).origin;
  const response = NextResponse.redirect(`${origin}/`);

  const supabase = createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    {
      cookies: {
        getAll() {
          const header = request.headers.get("cookie");
          if (!header) return [];
          return header.split(";").map((c) => {
            const eq = c.trim().indexOf("=");
            const name = eq === -1 ? c.trim() : c.trim().slice(0, eq);
            const value = eq === -1 ? "" : c.trim().slice(eq + 1);
            return { name, value };
          });
        },
        setAll(cookiesToSet: CookieToSet[]) {
          cookiesToSet.forEach(({ name, value, options }) =>
            response.cookies.set(name, value, options)
          );
        },
      },
    }
  );
  await supabase.auth.signOut();
  return response;
}
