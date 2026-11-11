<template>
 <DialogTransition :show="!!row" v-slot="{ returnFocus }">
  <NcDialog :container="null" :set-return-focus="returnFocus" class="cobudget-native-dialog cobudget-standard-dialog" :name="r.calendarConflict()" :no-close="busy" :close-on-click-outside="!busy" @closing="close">
   <p>{{ r.calendarConflictHint() }}</p>
   <fieldset v-for="(values, field) in row.calendar_conflict.fields" :key="field" class="calendar-conflict-field">
    <legend>{{ field === 'title' ? r.titleField() : field === 'description' ? r.description() : r.remindAt() }}</legend>
    <NcCheckboxRadioSwitch v-for="source in ['local', 'calendar']" :key="source" v-model="choices[field]" type="radio" :name="`calendar-conflict-${field}`" :value="source" :disabled="busy">
     <span class="calendar-conflict-value"><strong>{{ source === 'local' ? 'CoBudget' : $texts.settings.calendarChannel() }}</strong><span>{{ display(field, values[source]) }}</span></span>
    </NcCheckboxRadioSwitch>
   </fieldset>
   <NcNoteCard v-if="error" type="error">{{ error }}</NcNoteCard>
   <template #actions>
    <NcButton variant="tertiary" :disabled="busy" @click="close">{{ $texts.common.cancel() }}</NcButton>
    <NcButton variant="primary" :disabled="busy || !ready" @click="save"><template v-if="busy" #icon><NcLoadingIcon :size="20" /></template>{{ $texts.common.save() }}</NcButton>
   </template>
  </NcDialog>
 </DialogTransition>
</template>
<script>
import DialogTransition from './DialogTransition.vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import axios from '../services/http'
import { generateUrl } from '@nextcloud/router'
export default {
 components:{DialogTransition,NcDialog,NcButton,NcNoteCard,NcLoadingIcon,NcCheckboxRadioSwitch},
 props:{row:{type:Object,default:null}}, emits:['close','resolved'],
 data:()=>({choices:{},busy:false,error:''}),
 computed:{r(){return this.$texts.reminders},ready(){return this.row && Object.keys(this.row.calendar_conflict.fields).every(f=>['local','calendar'].includes(this.choices[f]))}},
 watch:{row(){this.choices={};this.error=''}},
 methods:{
  display(field,value){return field==='remind_at'?new Date(value*1000).toLocaleString():value || '—'},
  close(){if(!this.busy)this.$emit('close')},
  async save(){
   if(this.busy||!this.ready)return
   this.busy=true;this.error=''
   try {const result=await axios.post(generateUrl(`/apps/cobudget/api/reminders/${this.row.id}/calendar-resolve`),{token:this.row.calendar_conflict.token,choices:this.choices},{skipWorkspaceHeader:true});this.$emit('resolved',result.data.reminders)}
   catch(e){this.error=e.response?.data?.error||this.r.calendarRetryError()}
   finally{this.busy=false}
  },
 },
}
</script>
<style scoped>
.calendar-conflict-field { border: 0; margin: 16px 0; padding: 0; min-width: 0; }
.calendar-conflict-field legend { font-weight: var(--font-weight-element, 500); }
.calendar-conflict-value > span { font-weight: normal; }
.calendar-conflict-value { display: flex; flex-direction: column; gap: 4px; padding-block: 4px; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
