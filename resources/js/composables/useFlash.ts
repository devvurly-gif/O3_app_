import { ref } from 'vue'

/**
 * Composable for flash success / error messages.
 * Replaces the duplicated flash() / flashError() pattern found in 6+ pages.
 */
export function useFlash(successDuration = 4000, errorDuration = 5000) {
  const successMsg = ref('')
  const errorMsg = ref('')

  function flash(msg: string) {
    successMsg.value = msg
    setTimeout(() => {
      successMsg.value = ''
    }, successDuration)
  }

  function flashError(msg: string) {
    errorMsg.value = msg
    setTimeout(() => {
      errorMsg.value = ''
    }, errorDuration)
  }

  function clearFlash() {
    successMsg.value = ''
    errorMsg.value = ''
  }

  return { successMsg, errorMsg, flash, flashError, clearFlash }
}
