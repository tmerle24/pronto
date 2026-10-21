package de.tiamopizza.pronto;

import android.app.Application;
import com.stripe.stripeterminal.TerminalApplicationDelegate;
import com.stripe.stripeterminal.taptopay.TapToPay;

/** Required by Stripe Terminal: lifecycle delegate at app start, nothing else in the Tap to Pay process. */
public class MainApplication extends Application {

    @Override
    public void onCreate() {
        super.onCreate();
        if (TapToPay.isInTapToPayProcess()) {
            return;
        }
        TerminalApplicationDelegate.onCreate(this);
    }
}
