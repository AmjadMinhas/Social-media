import { ref } from 'vue';

export function useAlertModal() {
  const isOpenAlert = ref(false);
  const selectedItem = ref(null);
  const alertMessage = ref('');
  const alertCallback = ref(null);
  const alertArgs = ref([]);

  function openAlert(message, callback = null, args = []) {
    isOpenAlert.value = true;
    alertMessage.value = message;
    alertCallback.value = callback;
    alertArgs.value = args;
    selectedItem.value = { message, callback, args };
  }

  async function confirmAlert() {
    try {
      isOpenAlert.value = false;
      
      if (alertCallback.value) {
        // If callback is provided, call it with args
        if (Array.isArray(alertArgs.value) && alertArgs.value.length > 0) {
          await alertCallback.value(...alertArgs.value);
        } else if (alertArgs.value.length === 1) {
          await alertCallback.value(alertArgs.value[0]);
        } else {
          await alertCallback.value();
        }
      } else if (selectedItem.value && selectedItem.value.callback) {
        // Fallback for old pattern
        const { callback, args } = selectedItem.value;
        if (Array.isArray(args) && args.length > 0) {
          await callback(...args);
        } else if (args && args.length === 1) {
          await callback(args[0]);
        } else {
          await callback();
        }
      }
    } catch (error) {
      // Handle error
      console.error('Alert confirmation error:', error);
    } finally {
      selectedItem.value = null;
      alertMessage.value = '';
      alertCallback.value = null;
      alertArgs.value = [];
    }
  }

  function closeAlert() {
    isOpenAlert.value = false;
    selectedItem.value = null;
    alertMessage.value = '';
    alertCallback.value = null;
    alertArgs.value = [];
  }

  return {
    isOpenAlert,
    selectedItem,
    alertMessage,
    openAlert,
    confirmAlert,
    closeAlert,
  };
}