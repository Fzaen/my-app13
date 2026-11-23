export const copyTextToClipboard = async (text) => {
    let clipboardFailure = null;

    if (typeof navigator.clipboard?.writeText === "function") {
        try {
            await navigator.clipboard.writeText(text);

            return;
        } catch (error) {
            clipboardFailure = error;
        }
    }

    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "");
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    const activeDialog = document.querySelector("dialog[open]");
    (activeDialog ?? document.body).appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;
    let fallbackFailure = null;

    try {
        copied = document.execCommand("copy");
    } catch (error) {
        fallbackFailure = error;
    } finally {
        textarea.remove();
    }

    if (!copied) {
        const failure = fallbackFailure ?? clipboardFailure;

        throw new Error(
            failure instanceof Error
                ? `Clipboard copy failed: ${failure.message}`
                : "Clipboard copy command was rejected.",
        );
    }
};
