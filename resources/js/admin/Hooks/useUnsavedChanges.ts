import { router } from '@inertiajs/react'
import { useHotkey } from '@tanstack/react-hotkeys'
import { useEffect, useId, useRef } from 'react'
import { useToast } from '../Components/AppToastProvider'

type Options = {
  dirty: boolean
  message?: string
  onReset: () => void
  onSave: () => void
  processing?: boolean
}

export default function useUnsavedChanges({
  dirty,
  message = 'You have unsaved changes. Are you sure you want to leave this page?',
  onReset,
  onSave,
  processing = false,
}: Options) {
  const toast = useToast()
  const toastId = `unsaved-changes-${useId()}`
  const onResetRef = useRef(onReset)
  const onSaveRef = useRef(onSave)
  onResetRef.current = onReset
  onSaveRef.current = onSave

  useHotkey('Mod+S', () => onSaveRef.current(), {
    enabled: dirty && !processing,
    ignoreInputs: false,
    preventDefault: true,
  })

  useEffect(() => {
    if (dirty) {
      toast.add({
        data: {
          actions: [
            { disabled: processing, label: 'Reset', onClick: () => onResetRef.current() },
            { disabled: processing, label: 'Save', onClick: () => onSaveRef.current(), variant: 'primary' },
          ],
        },
        id: toastId,
        timeout: 0,
        title: 'Unsaved changes',
      })
    } else {
      toast.close(toastId)
    }
  }, [dirty, processing, toast.add, toast.close, toastId])

  useEffect(() => () => toast.close(toastId), [toast.close, toastId])

  useEffect(() => {
    if (!dirty) return

    const removeBeforeListener = router.on('before', (event) => {
      if (event.detail.visit.method !== 'get') return
      return window.confirm(message)
    })

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
      event.returnValue = ''
    }

    window.addEventListener('beforeunload', handleBeforeUnload)

    return () => {
      removeBeforeListener()
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }
  }, [dirty, message])
}
