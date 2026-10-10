<?php

/**
 * JavaScript translations for shared hosted AI infrastructure.
 *
 * @since 3.0.0
 * @updated 3.0.0 Design-first loader copy for designing and adapting stages.
 * @updated 3.0.0 Soften retry/adapting loader copy so output refine never looks like a hard failure.
 * @updated 3.0.0 Progressive polishing preview copy while Pass 1 HTML is shown.
 * @updated 3.0.0 Designing patience hint while Pass 1 runs without a preview yet.
 * @updated 3.0.0 Pass 2 failure recovery copy (Try again / Back to brief).
 * @updated 3.0.0 Soften loader validating copy so users never see pipeline jargon.
 * @author Hossein <hossein@notifal.com>
 */

return [
    'connectionTitle' => __('Connect Notifal AI', 'notifal'),
    'connectionSubtitle' => __('Enter your details to unlock hosted AI generation with your monthly free credits.', 'notifal'),
    'connectionConnectedTitle' => __('Connected to Notifal AI', 'notifal'),
    'connectionEmailLabel' => __('Email address', 'notifal'),
    'connectionEmailPlaceholder' => __('john@company.com', 'notifal'),
    'connectionEmailHint' => __('Use the email tied to your Notifal account, or create one with a verification code.', 'notifal'),
    'connectionNameLabel' => __('Full name', 'notifal'),
    'connectionNamePlaceholder' => __('John Doe', 'notifal'),
    'connectionNameHint' => __('Used on your notifal.com account and so Noti can greet you by name.', 'notifal'),
    'connectionNameRequired' => __('Please enter your name.', 'notifal'),
    'connectionCodeLabel' => __('Verification code', 'notifal'),
    'connectionCodeHint' => __('Enter the six-digit code we sent to your email.', 'notifal'),
    'connectionCodeTitle' => __('Verify your email', 'notifal'),
    'connectionCodeSubtitle' => __('We sent a six-digit code to %s. Enter it below to finish connecting.', 'notifal'),
    'connectionDisclosure' => __('Your request details and generated results are securely shared with Notifal AI and may be used to improve generation quality for all users.', 'notifal'),
    'connectionDisclosureLink' => __('Privacy details', 'notifal'),
    'connectionNoPayment' => __('No credit card required', 'notifal'),
    'connectionSendCode' => __('Continue', 'notifal'),
    'connectionVerifyCode' => __('Verify and connect', 'notifal'),
    'connectionResendCode' => __('Resend code', 'notifal'),
    'connectionResendWait' => __('Resend available in %s', 'notifal'),
    'connectionDisconnect' => __('Disconnect account', 'notifal'),
    'connectionDisconnectConfirm' => __('Disconnect this site from your Notifal account? Hosted AI generation will stop until you connect again.', 'notifal'),
    'connectionChangeEmail' => __('Use a different email', 'notifal'),
    'connectionAcceptDisclosure' => __('Agree to our Terms and Conditions', 'notifal'),
    'connectionAcceptDisclosureHint' => __('I agree to the Terms and Conditions and understand how Notifal AI handles my request data.', 'notifal'),
    'connectionCroTipsLabel' => __('Send me CRO Tips and Techniques', 'notifal'),
    'connectionCroTipsHint' => __('Receive exclusive strategies to increase your sales and convert more visitors directly to your inbox.', 'notifal'),
    'connectionRequiredDisclosure' => __('Please accept the Terms and Conditions to continue.', 'notifal'),
    'connectionSending' => __('Connecting…', 'notifal'),
    'connectionVerifying' => __('Verifying your code…', 'notifal'),
    'connectionSuccess' => __('Your Notifal account is connected.', 'notifal'),
    'connectionDisconnected' => __('Account disconnected.', 'notifal'),
    'quotaRemaining' => __('%1$d of %2$d generations left this month', 'notifal'),
    'quotaPlanFree' => __('Free plan', 'notifal'),
    'quotaPlanPro' => __('Pro plan', 'notifal'),
    'siteProfileLabel' => __('Your industry or niche', 'notifal'),
    'siteProfileHint' => __('Saved once for smarter AI suggestions across Notifal.', 'notifal'),
    'siteProfileSave' => __('Save site profile', 'notifal'),
    'siteProfileSaved' => __('Site profile saved.', 'notifal'),
    'siteProfileEdit' => __('Edit', 'notifal'),
    'genericError' => __('Something went wrong. Please try again.', 'notifal'),
    'networkError' => __('Network error. Check your connection and try again.', 'notifal'),
    'loaderQueued' => __('Preparing your creative brief…', 'notifal'),
    'loaderConnecting' => __('Securing your Notifal AI connection…', 'notifal'),
    'loaderGenerating' => __('Notifal AI is crafting your notification…', 'notifal'),
    'loaderGeneratingMagic' => __('Generating magic', 'notifal'),
    'loaderGeneratingPatience' => __('This can take a few minutes. Please keep this window open and stay patient while Notifal AI finishes your draft.', 'notifal'),
    // Design-first Pass 1: free visual draft before Notifal adaptation.
    'loaderDesigning' => __('Designing your template…', 'notifal'),
    'loaderDesigningDetail' => __('Exploring layout, color, and visual hierarchy…', 'notifal'),
    // Shown under the magic stage while Pass 1 is still inventing the look.
    'loaderDesigningPatience' => __('Designing your layout now. Keep this window open while Notifal AI explores the look.', 'notifal'),
    // Design-first Pass 2: convert the draft into the secure Notifal contract.
    'loaderAdapting' => __('Polishing your design…', 'notifal'),
    'loaderAdaptingDetail' => __('Refining layout, structure, and final details…', 'notifal'),
    // Shown under the MacBook when Pass 1 HTML is already visible during adapting.
    'loaderPolishingPreview' => __('Your design is ready. Polishing the final details…', 'notifal'),
    'loaderRetrying' => __('Still refining your draft. Please keep this window open…', 'notifal'),
    // Shown while polling an existing UUID through a temporary network blip.
    'loaderConnectionRetry' => __('Connection is slow. Still checking your in-progress request…', 'notifal'),
    // Shown while Pass 2 is still adapting after a temporary format refine.
    'loaderAdaptingRetry' => __('Still polishing your design. Almost there…', 'notifal'),
    // Shown when the start call never reached Notifal AI and we are about to ask the user to retry.
    'loaderConnectionFailed' => __('Could not reach Notifal AI. Please try again.', 'notifal'),
    'loaderValidating' => __('Making sure everything looks right…', 'notifal'),
    'loaderImporting' => __('Preparing your draft for review…', 'notifal'),
    'loaderApplying' => __('Loading your template into the builder…', 'notifal'),
    'loaderFinishing' => __('Almost there, putting on the finishing touches…', 'notifal'),
    'loaderCancel' => __('Cancel generation', 'notifal'),
    'loaderCancelling' => __('Cancelling…', 'notifal'),
    'loaderCancellingMessage' => __('Cancelling your generation…', 'notifal'),
    // Shown under the cancelling message instead of the "finish your draft" patience hint.
    'loaderCancellingHint' => __('Stopping this request. You can try again when you are ready.', 'notifal'),
    'loaderBrand' => __('Notifal AI', 'notifal'),
    // Pass 2 failed after Pass 1 draft was visible: keep preview, offer Try again.
    'loaderPass2RecoveryTitle' => __('Could not complete', 'notifal'),
    'loaderPass2RecoveryHint' => __('Your design draft is still here. Try again to finish polishing, or go back to edit your brief.', 'notifal'),
    'loaderPass2RecoveryBack' => __('Back to brief', 'notifal'),
    'loaderPhaseBrief' => __('Preparing your brief', 'notifal'),
    'loaderPhaseLayout' => __('Shaping the layout', 'notifal'),
    'loaderPhaseCopy' => __('Writing the message', 'notifal'),
    'loaderPhaseStyle' => __('Applying brand style', 'notifal'),
    'loaderPhaseCraft' => __('Crafting the experience', 'notifal'),
    'loaderPhaseDesigning' => __('Designing your template', 'notifal'),
    'loaderPhaseAdapting' => __('Polishing your design', 'notifal'),
    'loaderPhaseValidate' => __('Final check', 'notifal'),
    'loaderPhaseImport' => __('Preparing your draft', 'notifal'),
    'loaderPhaseFinish' => __('Finishing touches', 'notifal'),
    'loaderPhaseCancel' => __('Cancelling generation', 'notifal'),
    'generationFailed' => __('We could not complete this request. Please try again.', 'notifal'),
    'generationRetry' => __('Try again', 'notifal'),
    'generationCancelled' => __('Generation cancelled.', 'notifal'),
    'feedbackHelpful' => __('Helpful', 'notifal'),
    'feedbackNeedsImprovement' => __('Needs improvement', 'notifal'),
    'feedbackThanks' => __('Thanks for your feedback.', 'notifal'),
    'readinessChecking' => __('Checking if Noti is ready…', 'notifal'),
    'unavailableTitle' => __('Noti is taking a short break', 'notifal'),
    'unavailableMessagePaused' => __('I\'m getting a quick upgrade right now, so I can\'t craft notifications for a moment. Thanks for your patience, I promise I\'ll be back soon with even better ideas!', 'notifal'),
    'unavailableMessageOffline' => __('I can\'t reach Notifal AI right now. Please check your connection and try again in a little while.', 'notifal'),
    'unavailableMessageUnavailable' => __('I\'m offline for a moment while we finish some improvements. Hang tight, I\'ll be ready to help again soon!', 'notifal'),
    'unavailableClose' => __('Got it', 'notifal'),
    'unavailableRetry' => __('Try again', 'notifal'),
];
