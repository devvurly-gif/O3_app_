import { ref, toValue } from 'vue'
import type { MaybeRefOrGetter, Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useProductStore } from '@/stores/product'

/**
 * Les medias d'un produit : images, liens video, documents joints.
 *
 * Sept fonctions et huit refs occupaient le dernier quart de Products.vue,
 * toutes autour de la meme idee — televerser, designer l'image principale,
 * supprimer — et toutes suspendues au produit en cours d'edition.
 *
 * Ce produit est passe par un getter plutot que par une valeur : la modale
 * s'ouvre avant que `/products/{id}` reponde, et l'ecran remplace alors sa
 * cible par la version complete. Un getter suit ce remplacement.
 */
export interface ProductMediaOptions {
  /** Le produit en cours d'edition, ou null en creation. */
  product: MaybeRefOrGetter<any>
  /** Remonte un message a l'utilisateur. */
  notify: (message: string, level: 'success' | 'error') => void
}

export function useProductMedia(options: ProductMediaOptions) {
  const { t } = useI18n()
  const store = useProductStore()

  const images: Ref<any[]> = ref([])
  const videos: Ref<any[]> = ref([])
  const documents: Ref<any[]> = ref([])

  const uploadingImage = ref(false)
  const uploadingDocument = ref(false)
  const addingVideo = ref(false)

  const newVideoTitle = ref('')
  const newVideoUrl = ref('')

  const productId = (): number | null => toValue(options.product)?.id ?? null

  /** Recharge les trois listes depuis la ligne ouverte (copies locales). */
  function reset(product?: any): void {
    images.value = [...(product?.images ?? [])]
    videos.value = [...(product?.videos ?? [])]
    documents.value = [...(product?.documents ?? [])]
  }

  // ── Images ────────────────────────────────────────────────────────────────
  async function handleImageUpload(e: any): Promise<void> {
    const files = e.target.files
    const id = productId()
    if (!files || !id) return
    e.target.value = '' // reset input

    uploadingImage.value = true
    try {
      // Support multiple files
      for (let i = 0; i < files.length; i++) {
        const fd = new FormData()
        fd.append('image', files[i])
        fd.append('isPrimary', images.value.length === 0 && i === 0 ? '1' : '0')
        const img = await store.uploadImage(id, fd)
        images.value.push(img)
        if (img.isPrimary) {
          images.value.forEach((im: any) => {
            if (im.id !== img.id) im.isPrimary = false
          })
        }
      }
      options.notify(`${files.length} image(s) uploaded`, 'success')
      await store.fetchPage()
    } catch {
      options.notify(t('products.imageFailed'), 'error')
    } finally {
      uploadingImage.value = false
    }
  }

  async function setPrimary(img: any): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      await store.setPrimaryImage(id, img.id)
      images.value.forEach((i: any) => {
        i.isPrimary = i.id === img.id
      })
      await store.fetchPage()
    } catch {
      options.notify(t('common.failedSave'), 'error')
    }
  }

  async function deleteImage(img: any): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      await store.deleteImage(id, img.id)
      images.value = images.value.filter((i: any) => i.id !== img.id)
      options.notify(t('products.imageDeleted'), 'success')
      await store.fetchPage()
    } catch {
      options.notify(t('common.failedDelete'), 'error')
    }
  }

  // ── Liens video ───────────────────────────────────────────────────────────
  async function addVideo(): Promise<void> {
    const url = newVideoUrl.value.trim()
    const id = productId()
    if (!url || !id) return

    addingVideo.value = true
    try {
      const video = await store.addVideo(id, {
        title: newVideoTitle.value.trim() || undefined,
        url,
      })
      videos.value.push(video)
      newVideoTitle.value = ''
      newVideoUrl.value = ''
      options.notify(t('products.videoAdded'), 'success')
    } catch {
      options.notify(t('products.videoFailed'), 'error')
    } finally {
      addingVideo.value = false
    }
  }

  async function deleteVideo(video: any): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      await store.deleteVideo(id, video.id)
      videos.value = videos.value.filter((v: any) => v.id !== video.id)
      options.notify(t('products.videoDeleted'), 'success')
    } catch {
      options.notify(t('common.failedDelete'), 'error')
    }
  }

  // ── Documents ─────────────────────────────────────────────────────────────
  async function handleDocumentUpload(e: any): Promise<void> {
    const files = e.target.files
    const id = productId()
    if (!files || !id) return
    e.target.value = ''

    uploadingDocument.value = true
    try {
      for (let i = 0; i < files.length; i++) {
        const fd = new FormData()
        fd.append('file', files[i])
        const doc = await store.uploadDocument(id, fd)
        documents.value.push(doc)
      }
      options.notify(t('products.documentUploaded'), 'success')
    } catch {
      options.notify(t('products.documentFailed'), 'error')
    } finally {
      uploadingDocument.value = false
    }
  }

  async function deleteDocument(doc: any): Promise<void> {
    const id = productId()
    if (!id) return
    try {
      await store.deleteDocument(id, doc.id)
      documents.value = documents.value.filter((d: any) => d.id !== doc.id)
      options.notify(t('products.documentDeleted'), 'success')
    } catch {
      options.notify(t('common.failedDelete'), 'error')
    }
  }

  return {
    images,
    videos,
    documents,
    uploadingImage,
    uploadingDocument,
    addingVideo,
    newVideoTitle,
    newVideoUrl,
    reset,
    handleImageUpload,
    setPrimary,
    deleteImage,
    addVideo,
    deleteVideo,
    handleDocumentUpload,
    deleteDocument,
  }
}
